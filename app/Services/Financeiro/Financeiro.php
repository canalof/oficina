<?php

namespace App\Services\Financeiro;

use App\Models\Caixa;
use App\Models\CaixaMovimento;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\OrdemServico;
use App\Support\Dinheiro;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

class Financeiro
{
    public const FORMAS = [
        'dinheiro' => 'Dinheiro', 'pix' => 'Pix', 'debito' => 'Cartão de débito', 'credito' => 'Cartão de crédito',
        'boleto' => 'Boleto', 'prazo' => 'A prazo (fiado)', 'transferencia' => 'Transferência',
    ];

    /** Formas que entram na hora (as demais viram conta a receber). */
    public const IMEDIATAS = ['dinheiro', 'pix', 'debito', 'credito', 'transferencia'];

    /**
     * Registra o pagamento de uma OS.
     *
     * @param  list<array{forma:string,valor:int,parcelas?:int,vencimento?:string}>  $linhas
     */
    public function receberOs(OrdemServico $os, array $linhas): void
    {
        // Nunca descartar silenciosamente valores negativos/zerados: isso
        // poderia alterar a composição do pagamento sem o usuário perceber.
        if ($linhas === []) {
            throw new DomainException('Informe ao menos um pagamento válido.');
        }
        $soma = 0;
        foreach ($linhas as $linha) {
            if (!is_array($linha) || !isset($linha['valor']) || !is_int($linha['valor']) || $linha['valor'] <= 0) {
                throw new DomainException('Cada pagamento deve ter valor positivo em centavos.');
            }
            if ($soma > PHP_INT_MAX - $linha['valor']) {
                throw new DomainException('Total dos pagamentos excede o limite permitido.');
            }
            $soma += $linha['valor'];
        }
        foreach ($linhas as $l) {
            $this->validarForma($l['forma'] ?? '');
            if (! in_array($l['forma'], self::IMEDIATAS, true)) {
                $parcelas = $l['parcelas'] ?? 1;
                if (filter_var($parcelas, FILTER_VALIDATE_INT) === false || (int) $parcelas < 1 || (int) $parcelas > 24) {
                    throw new DomainException('O parcelamento deve ter entre 1 e 24 parcelas.');
                }
                if ($l['valor'] < (int) $parcelas) {
                    throw new DomainException('O valor é insuficiente para gerar parcelas positivas.');
                }
                if (!empty($l['vencimento']) && (! is_string($l['vencimento']) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $l['vencimento']) || !\DateTimeImmutable::createFromFormat('!Y-m-d', $l['vencimento']) || \DateTimeImmutable::createFromFormat('!Y-m-d', $l['vencimento'])->format('Y-m-d') !== $l['vencimento'])) {
                    throw new DomainException('Informe o vencimento no formato AAAA-MM-DD.');
                }
            }
        }
        DB::transaction(function () use ($os, $linhas, $soma) {
            $os = OrdemServico::whereKey($os->id)->lockForUpdate()->firstOrFail();
            if ($os->status !== \App\Support\StatusOs::Pronta) {
                throw new DomainException('A OS precisa estar pronta para receber pagamento.');
            }
            if ($os->recebido_em) {
                throw new DomainException('O pagamento desta OS já foi registrado.');
            }
            if ($soma !== $os->total_centavos) {
                throw new DomainException('A soma dos pagamentos ('.Dinheiro::formatar($soma).') precisa ser igual ao total da OS ('.$os->totalFormatado().').');
            }
            $caixa = $this->caixaAbertoBloqueado();
            if (! $caixa && collect($linhas)->contains(fn ($l) => $l['forma'] === 'dinheiro')) {
                throw new DomainException('Abra o caixa para receber em dinheiro.');
            }
            $desc = 'OS '.$os->numeroFormatado().' · '.$os->veiculo->placaFormatada();
            foreach ($linhas as $l) {
                $forma = $l['forma'];
                if (in_array($forma, self::IMEDIATAS, true)) {
                    $conta = ContaReceber::create([
                        'cliente_id' => $os->cliente_id, 'ordem_servico_id' => $os->id, 'descricao' => $desc,
                        'parcela' => $forma === 'credito' && ($l['parcelas'] ?? 1) > 1 ? $l['parcelas'].'x no cartão' : null,
                        'valor_centavos' => $l['valor'], 'vencimento' => today(), 'forma' => $forma,
                        'status' => 'recebida', 'recebido_em' => now(), 'forma_recebida' => $forma,
                    ]);
                    $this->lancarNoCaixa($caixa, 'recebimento', $forma, $l['valor'], $desc, contaReceber: $conta->id);

                    continue;
                }
                // boleto / a prazo: parcelas mensais
                $n = max(1, min(24, (int) ($l['parcelas'] ?? 1)));
                $primeiro = Carbon::parse($l['vencimento'] ?? today()->addDays(30));
                $base = intdiv($l['valor'], $n);
                for ($i = 0; $i < $n; $i++) {
                    ContaReceber::create([
                        'cliente_id' => $os->cliente_id, 'ordem_servico_id' => $os->id, 'descricao' => $desc,
                        'parcela' => $n > 1 ? ($i + 1).'/'.$n : null,
                        'valor_centavos' => $i === $n - 1 ? $l['valor'] - $base * ($n - 1) : $base,
                        'vencimento' => $primeiro->copy()->addMonthsNoOverflow($i), 'forma' => $forma,
                    ]);
                }
            }
            $os->recebido_em = now();
            $os->save();
            $os->registrar('Pagamento registrado: '.collect($linhas)->map(fn ($l) => self::FORMAS[$l['forma']].' '.Dinheiro::formatar($l['valor']))->join(', '));
        });
    }

    public function receberConta(ContaReceber $conta, string $forma): void
    {
        $this->validarFormaRecebimento($forma);
        DB::transaction(function () use ($conta, $forma) {
            $conta = ContaReceber::whereKey($conta->id)->lockForUpdate()->firstOrFail();
            if ($conta->status !== 'aberta') {
                throw new DomainException('Esta conta não está em aberto.');
            }
            $caixa = $this->caixaAbertoBloqueado();
            if ($forma === 'dinheiro' && ! $caixa) {
                throw new DomainException('Abra o caixa para receber em dinheiro.');
            }
            $conta->update(['status' => 'recebida', 'recebido_em' => now(), 'forma_recebida' => $forma]);
            $this->lancarNoCaixa($caixa, 'recebimento', $forma, $conta->valor_centavos, 'Recebido: '.$conta->descricao.($conta->parcela ? ' '.$conta->parcela : ''), contaReceber: $conta->id);
        });
    }

    /**
     * Estorno de um recebimento (cliente devolveu, Pix voltou, cartão contestado).
     * Nada é apagado: a conta fica "estornada" e o caixa registra a saída.
     */
    public function estornarRecebimento(ContaReceber $conta, string $motivo): void
    {
        // A instância recebida pode estar desatualizada: conferir o status
        // somente após reler e bloquear o título dentro da transação.
        if (mb_strlen(trim($motivo)) < 5) {
            throw new DomainException('Explique o motivo do estorno.');
        }
        DB::transaction(function () use ($conta, $motivo) {
            $conta = ContaReceber::whereKey($conta->id)->lockForUpdate()->firstOrFail();
            if ($conta->status !== 'recebida') {
                throw new DomainException('Só dá para estornar uma conta recebida.');
            }
            $caixa = $this->caixaAbertoBloqueado();
            if ($conta->forma_recebida === 'dinheiro' && ! $caixa) {
                throw new DomainException('Abra o caixa para devolver dinheiro.');
            }
            if ($conta->forma_recebida === 'dinheiro' && $caixa->dinheiroEsperado() < $conta->valor_centavos) {
                throw new DomainException('Não há dinheiro suficiente no caixa para este estorno.');
            }
            $conta->update(['status' => 'estornada']);
            $this->lancarNoCaixa($caixa, 'estorno', $conta->forma_recebida ?: 'pix', $conta->valor_centavos, 'Estorno: '.$conta->descricao.' ('.mb_substr($motivo, 0, 80).')', contaReceber: $conta->id);
            if ($conta->ordem_servico_id && ($os = OrdemServico::find($conta->ordem_servico_id))) {
                $os->registrar('Recebimento estornado: '.Dinheiro::formatar($conta->valor_centavos).' ('.mb_substr($motivo, 0, 120).')');
            }
        });
    }

    public function pagarConta(ContaPagar $conta, string $forma): void
    {
        $this->validarFormaPagamento($forma);
        DB::transaction(function () use ($conta, $forma) {
            $conta = ContaPagar::whereKey($conta->id)->lockForUpdate()->firstOrFail();
            if ($conta->status !== 'aberta') {
                throw new DomainException('Esta conta não está em aberto.');
            }
            $caixa = $this->caixaAbertoBloqueado();
            if ($forma === 'dinheiro' && ! $caixa) {
                throw new DomainException('Abra o caixa para pagar em dinheiro.');
            }
            if ($forma === 'dinheiro' && $caixa->dinheiroEsperado() < $conta->valor_centavos) {
                throw new DomainException('Não há dinheiro suficiente no caixa ('.Dinheiro::formatar($caixa->dinheiroEsperado()).').');
            }
            $conta->update(['status' => 'paga', 'pago_em' => now(), 'forma_paga' => $forma]);
            $this->lancarNoCaixa($caixa, 'pagamento', $forma, $conta->valor_centavos, 'Pago: '.$conta->descricao, contaPagar: $conta->id);
        });
    }

    private function validarForma(string $forma): void
    {
        if (! array_key_exists($forma, self::FORMAS)) {
            throw new DomainException('Forma de pagamento inválida.');
        }
    }

    private function validarFormaRecebimento(string $forma): void
    {
        $this->validarForma($forma);
        if (! in_array($forma, self::IMEDIATAS, true)) {
            throw new DomainException('Selecione uma forma de recebimento imediato.');
        }
    }

    private function validarFormaPagamento(string $forma): void
    {
        $this->validarForma($forma);
        // Boleto pode quitar um título a pagar, mas não representa um
        // recebimento imediato de cliente nem movimenta a gaveta em dinheiro.
        if (! in_array($forma, self::IMEDIATAS, true) && $forma !== 'boleto') {
            throw new DomainException('Selecione uma forma de recebimento imediato.');
        }
    }

    /** Mantém a gaveta bloqueada até o commit do recebimento/pagamento. */
    private function caixaAbertoBloqueado(): ?Caixa
    {
        return Caixa::query()->whereNull('fechado_em')->latest('id')->lockForUpdate()->first();
    }

    /** Lança no caixa aberto (se houver). Pix, cartão e transferência ficam registrados mesmo sem caixa aberto. */
    private function lancarNoCaixa(?Caixa $caixa, string $tipo, string $forma, int $valor, string $desc, ?int $contaReceber = null, ?int $contaPagar = null): void
    {
        if (! $caixa) {
            return;
        }
        CaixaMovimento::create([
            'caixa_id' => $caixa->id, 'user_id' => auth()->id(), 'tipo' => $tipo, 'forma' => $forma,
            'valor_centavos' => $valor, 'descricao' => mb_substr($desc, 0, 200),
            'conta_receber_id' => $contaReceber, 'conta_pagar_id' => $contaPagar, 'created_at' => now(),
        ]);
    }
}
