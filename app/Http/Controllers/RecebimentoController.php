<?php

namespace App\Http\Controllers;

use App\Models\OrdemServico;
use App\Services\Financeiro\Financeiro;
use App\Support\Dinheiro;
use DomainException;
use Illuminate\Http\Request;

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
            if ($os->total_centavos > 0) {
                $financeiro->receberOs($os, $linhas);
            }
            if ($request->boolean('entregar')) {
                $os->refresh()->entregar();

                return back()->with('ok', 'Pagamento registrado e carro entregue.'.($os->garantiaAte() ? ' Garantia até '.$os->garantiaAte()->format('d/m/Y').'.' : ''));
            }
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage())->withInput();
        }

        return back()->with('ok', 'Pagamento registrado.');
    }
}
