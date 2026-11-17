<?php

namespace App\Http\Controllers;

use App\Models\OrdemServico;
use App\Services\Financeiro\Financeiro;
use App\Support\Dinheiro;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Recebe o pagamento de uma OS pronta (e entrega o carro, se pedido). */
class RecebimentoController extends Controller
{
    public function store(Request $request, OrdemServico $os, Financeiro $financeiro)
    {
        $this->authorize('caixa.operar');
        $linhas = [];
        foreach ((array) $request->input('pagamentos', []) as $p) {
            $linhas[] = [
                'forma' => (string) ($p['forma'] ?? ''),
                'valor' => Dinheiro::paraCentavos($p['valor'] ?? ''),
                'parcelas' => (int) ($p['parcelas'] ?? 1),
                'vencimento' => $p['vencimento'] ?? null,
            ];
        }

        try {
            // Recebimento + entrega são uma única operação atômica. Se faltar
            // uma peça na entrega, nenhum título/caixa/recebimento é confirmado.
            // A própria OS é bloqueada antes das operações financeiras.
            $entregar = $request->boolean('entregar');
            DB::transaction(function () use ($os, $financeiro, $linhas, $entregar) {
                $atual = OrdemServico::whereKey($os->id)->lockForUpdate()->firstOrFail();
                if ($atual->status !== \App\Support\StatusOs::Pronta) {
                    throw new DomainException('A OS precisa estar pronta para registrar o recebimento.');
                }
                if ($atual->total_centavos > 0) {
                    $financeiro->receberOs($atual, $linhas);
                } elseif ($linhas !== []) {
                    throw new DomainException('Uma OS sem valor não aceita pagamentos.');
                }
                if ($entregar) {
                    $atual->refresh()->entregar();
                }
            }, 3);
            if ($entregar) {
                $os->refresh();
                return back()->with('ok', 'Pagamento registrado e carro entregue.'.($os->garantiaAte() ? ' Garantia até '.$os->garantiaAte()->format('d/m/Y').'.' : ''));
            }
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage())->withInput();
        }

        return back()->with('ok', 'Pagamento registrado.');
    }
}
