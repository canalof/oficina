<?php

namespace App\Services\Financeiro;

use App\Models\ContaPagar;
use App\Models\ContaReceber;
use Illuminate\Support\Collection;

/**
 * Sugestões estritamente consultivas. Nunca baixa, liquida ou concilia títulos.
 * Os escopos globais de empresa dos modelos continuam ativos.
 */
class SugestoesConciliacao
{
    public function paraMovimentos(Collection $movimentos): array
    {
        $resultado = [];
        foreach ($movimentos as $movimento) {
            if ($movimento->status_conciliacao !== 'pendente' || (int) $movimento->valor_centavos === 0) {
                continue;
            }
            $credito = (int) $movimento->valor_centavos > 0;
            $classe = $credito ? ContaReceber::class : ContaPagar::class;
            $campo = $credito ? 'conta_receber_id' : 'conta_pagar_id';
            $data = $movimento->data_movimento;
            if (!$data) {
                continue;
            }
            $resultado[$movimento->id] = $classe::query()
                ->where('status', $credito ? 'recebida' : 'paga')
                ->where('valor_centavos', abs((int) $movimento->valor_centavos))
                ->whereBetween($credito ? 'recebido_em' : 'pago_em', [
                    $data->copy()->subDays(7)->startOfDay(),
                    $data->copy()->addDays(7)->endOfDay(),
                ])
                ->whereNotIn('id', function ($query) use ($campo) {
                    $query->select($campo)->from('movimentos_bancarios')
                        ->where('status_conciliacao', 'conciliado')
                        ->whereNotNull($campo)
                        ->where('empresa_id', \App\Support\Tenant::id());
                })
                ->orderBy($credito ? 'recebido_em' : 'pago_em')
                ->limit(5)
                ->get(['id', 'descricao', 'valor_centavos']);
        }
        return $resultado;
    }
}
