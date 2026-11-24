<?php

namespace App\Services\Estoque;

use App\Models\Balanco;
use App\Models\Produto;
use Illuminate\Support\Facades\DB;

/**
 * Aplica um balanço: para cada produto contado, ajusta o saldo para a quantidade contada
 * (só quando há diferença) com Produto::movimentar(), origem "balanco". Tudo ou nada.
 */
final class AplicarBalanco
{
    /**
     * @param  array<int, array{contado: float, visto: ?float}>  $contagens  produto_id => contagem
     */
    public static function executar(array $contagens, ?string $observacao, ?int $userId): Balanco
    {
        if ($contagens === []) {
            throw new \DomainException('Informe a quantidade contada de pelo menos um produto.');
        }

        return DB::transaction(function () use ($contagens, $observacao, $userId) {
            $balanco = Balanco::create(['user_id' => $userId, 'observacao' => $observacao]);
            $motivo = 'Balanço nº '.$balanco->id.($observacao ? ' · '.$observacao : '');
            $ajustados = 0;
            $valor = 0;

            // Ordem fixa de bloqueio evita impasse com outra operação de estoque
            ksort($contagens);
            foreach ($contagens as $produtoId => $c) {
                $produto = Produto::whereKey($produtoId)->lockForUpdate()->first();
                if (! $produto) {
                    throw new \DomainException('Um dos produtos da contagem não existe mais nesta oficina.');
                }
                $sistema = (float) $produto->estoque;
                $dif = round($c['contado'] - $sistema, 3);
                $custo = (int) $produto->custo_centavos;

                $balanco->itens()->create([
                    'produto_id' => $produto->id, 'saldo_visto' => $c['visto'], 'saldo_sistema' => $sistema,
                    'contado' => $c['contado'], 'diferenca' => $dif, 'custo_unitario_centavos' => $custo,
                ]);

                if ($dif != 0.0) {
                    $produto->movimentar($dif, 'ajuste', 'balanco', $balanco->id, null, mb_substr('Contagem: '.Produto::formatarQtd($c['contado']).' · '.$motivo, 0, 255));
                    $ajustados++;
                    $valor += (int) round($dif * $custo);
                }
            }

            $balanco->update(['produtos_contados' => count($contagens), 'produtos_ajustados' => $ajustados, 'diferenca_valor_centavos' => $valor]);

            return $balanco;
        });
    }
}
