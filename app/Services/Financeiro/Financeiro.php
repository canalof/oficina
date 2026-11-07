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
        if ($os->recebido_em) {
            throw new DomainException('O pagamento desta OS já foi registrado.');
        }
        $linhas = array_values(array_filter($linhas, fn ($l) => ($l['valor'] ?? 0) > 0));
        $soma = array_sum(array_column($linhas, 'valor'));
        if ($soma !== $os->total_centavos) {
            throw new DomainException('A soma dos pagamentos ('.Dinheiro::formatar($soma).') precisa ser igual ao total da OS ('.$os->totalFormatado().').');
        }
        foreach ($linhas as $l) {
            if (! isset(self::FORMAS[$l['forma']])) {
                throw new DomainException('Forma de pagamento inválida.');
            }
        }
        $caixa = Caixa::aberto();
        if (! $caixa && collect($linhas)->contains(fn ($l) => $l['forma'] === 'dinheiro')) {
            throw new DomainException('Abra o caixa para receber em dinheiro.');
        }

        DB::transaction(function () use ($os, $linhas, $caixa) {
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
        if ($conta->status !== 'aberta') {
            throw new DomainException('Esta conta não está em aberto.');
        }
        $caixa = Caixa::aberto();
        if ($forma === 'dinheiro' && ! $caixa) {
            throw new DomainException('Abra o caixa para receber em dinheiro.');
        }
        DB::transaction(function () use ($conta, $forma, $caixa) {
            $conta->update(['status' => 'recebida', 'recebido_em' => now(), 'forma_recebida' => $forma]);
            $this->lancarNoCaixa($caixa, 'recebimento', $forma, $conta->valor_centavos, 'Recebido: '.$conta->descricao.($conta->parcela ? ' '.$conta->parcela : ''), contaReceber: $conta->id);
        });
    }

    public function pagarConta(ContaPagar $conta, string $forma): void
    {
        if ($conta->status !== 'aberta') {
            throw new DomainException('Esta conta não está em aberto.');
        }
        $caixa = Caixa::aberto();
        if ($forma === 'dinheiro' && ! $caixa) {
            throw new DomainException('Abra o caixa para pagar em dinheiro.');
        }
        if ($forma === 'dinheiro' && $caixa->dinheiroEsperado() < $conta->valor_centavos) {
            throw new DomainException('Não há dinheiro suficiente no caixa ('.Dinheiro::formatar($caixa->dinheiroEsperado()).').');
        }
        DB::transaction(function () use ($conta, $forma, $caixa) {
            $conta->update(['status' => 'paga', 'pago_em' => now(), 'forma_paga' => $forma]);
            $this->lancarNoCaixa($caixa, 'pagamento', $forma, $conta->valor_centavos, 'Pago: '.$conta->descricao, contaPagar: $conta->id);
        });
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
