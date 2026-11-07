<?php

namespace App\Services\Cobranca;

use App\Models\Empresa;
use App\Models\FaturaPlataforma;
use App\Models\Plano;
use App\Models\WebhookRecebido;
use App\Support\Tenant;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Ciclo da assinatura: teste → ativa → atrasada → suspensa → reativada. */
class Assinaturas
{
    public function gateway(): Gateway
    {
        return config('cobranca.provedor') === 'asaas' ? new Asaas : new CobrancaDemonstracao;
    }

    public function assinar(Empresa $empresa, Plano $plano, string $email): void
    {
        if (! $plano->ativo) {
            throw new DomainException('Plano indisponível.');
        }
        $this->verificarLimites($empresa, $plano);
        $gw = $this->gateway();
        $r = $gw->assinar($empresa, $plano, $email);
        $dados = ['plano_id' => $plano->id, 'cobranca_cliente_id' => $r['cliente_id'], 'cobranca_assinatura_id' => $r['assinatura_id']];

        if ($gw->nome() === 'demonstracao') {
            // Sem cobrança real: ativa na hora
            $dados += ['assinatura_status' => 'ativa', 'proximo_vencimento' => today()->addMonth(), 'suspensa_em' => null];
        }
        $empresa->update($dados);
    }

    /** Não deixa descer para um plano menor que o uso atual. */
    public function verificarLimites(Empresa $empresa, Plano $plano): void
    {
        $usuarios = $empresa->usuarios()->where('ativo', true)->count();
        if ($plano->limite_usuarios !== null && $usuarios > $plano->limite_usuarios) {
            throw new DomainException("O plano {$plano->nome} permite {$plano->limite_usuarios} usuários e a oficina tem {$usuarios} ativos. Desative alguns antes de trocar.");
        }
    }

    /** Webhook do Asaas: grava, ignora repetido e aplica. Idempotente. */
    public function processarWebhook(array $evento): string
    {
        $id = (string) ($evento['id'] ?? '');
        $tipo = (string) ($evento['event'] ?? '');
        if ($id === '' || $tipo === '') {
            return 'ignorado';
        }

        return DB::transaction(function () use ($id, $tipo, $evento) {
            $registro = WebhookRecebido::firstOrCreate(['provedor' => 'asaas', 'evento_id' => $id], ['evento' => $tipo, 'payload' => $evento]);
            if (! $registro->wasRecentlyCreated && $registro->processado_em) {
                return 'repetido';
            }

            $p = $evento['payment'] ?? null;
            $empresa = $p ? $this->empresaDoPagamento($p) : null;
            if (! $empresa) {
                $registro->update(['processado_em' => now(), 'erro' => 'Empresa não encontrada']);

                return 'sem_empresa';
            }

            Tenant::com($empresa->id, function () use ($tipo, $p, $empresa) {
                $fatura = FaturaPlataforma::firstOrNew(['externo_id' => $p['id']]);
                $fatura->fill([
                    'empresa_id' => $empresa->id,
                    'valor_centavos' => (int) round(((float) ($p['value'] ?? 0)) * 100),
                    'vencimento' => $p['dueDate'] ?? $fatura->vencimento,
                    'url' => $p['invoiceUrl'] ?? $fatura->url,
                ]);
                match ($tipo) {
                    'PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED' => $this->pago($empresa, $fatura, $p),
                    'PAYMENT_OVERDUE' => $this->atrasado($empresa, $fatura),
                    'PAYMENT_REFUNDED', 'PAYMENT_DELETED' => $fatura->fill(['status' => $tipo === 'PAYMENT_REFUNDED' ? 'estornada' : 'cancelada']),
                    default => $fatura->status ??= 'pendente',
                };
                $fatura->status ??= 'pendente';
                $fatura->save();
            });
            $registro->update(['processado_em' => now(), 'erro' => null]);

            return 'ok';
        });
    }

    private function empresaDoPagamento(array $p): ?Empresa
    {
        if (! empty($p['subscription']) && ($e = Empresa::where('cobranca_assinatura_id', $p['subscription'])->first())) {
            return $e;
        }
        if (preg_match('/^empresa:(\d+)$/', (string) ($p['externalReference'] ?? ''), $m)) {
            return Empresa::find((int) $m[1]);
        }

        return ! empty($p['customer']) ? Empresa::where('cobranca_cliente_id', $p['customer'])->first() : null;
    }

    private function pago(Empresa $empresa, FaturaPlataforma $fatura, array $p): void
    {
        $fatura->fill(['status' => 'paga', 'paga_em' => now()]);
        $venc = isset($p['dueDate']) ? Carbon::parse($p['dueDate'])->addMonthNoOverflow() : today()->addMonth();
        $empresa->update(['assinatura_status' => 'ativa', 'suspensa_em' => null, 'proximo_vencimento' => $venc]);
    }

    private function atrasado(Empresa $empresa, FaturaPlataforma $fatura): void
    {
        $fatura->fill(['status' => 'vencida']);
        if (in_array($empresa->assinatura_status, ['ativa', 'trial'], true)) {
            $empresa->update(['assinatura_status' => 'inadimplente']);
        }
    }

    /** Rotina diária: teste vencido sem assinatura e atraso além da tolerância viram "suspensa". */
    public function verificarVencimentos(): int
    {
        $n = 0;
        foreach (Empresa::where('assinatura_status', 'trial')->whereNotNull('trial_ate')->where('trial_ate', '<', today())->get() as $e) {
            $e->update(['assinatura_status' => 'suspensa', 'suspensa_em' => now()]);
            $n++;
        }
        $limite = today()->subDays(config('cobranca.dias_tolerancia'));
        foreach (Empresa::where('assinatura_status', 'inadimplente')->get() as $e) {
            $maisAntiga = Tenant::com($e->id, fn () => FaturaPlataforma::where('status', 'vencida')->min('vencimento'));
            if ($maisAntiga && Carbon::parse($maisAntiga)->lt($limite)) {
                $e->update(['assinatura_status' => 'suspensa', 'suspensa_em' => now()]);
                $n++;
            }
        }

        return $n;
    }
}
