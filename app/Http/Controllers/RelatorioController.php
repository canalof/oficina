<?php

namespace App\Http\Controllers;

use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;
use App\Support\StatusOs;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Resumo do mês: o que entrou, o que saiu, o que vendeu. */
class RelatorioController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('financeiro.ver');
        $m = (string) $request->input('mes', now()->format('Y-m'));
        $ini = preg_match('/^\d{4}-\d{2}$/', $m) ? Carbon::createFromFormat('Y-m-d', $m.'-01')->startOfDay() : now()->startOfMonth();
        $fim = $ini->copy()->endOfMonth();

        $entregues = OrdemServico::where('status', StatusOs::Entregue->value)->whereBetween('entregue_em', [$ini, $fim]);
        $idsOs = (clone $entregues)->pluck('id');

        $recebido = ContaReceber::where('status', 'recebida')->whereBetween('recebido_em', [$ini, $fim]);
        $pago = ContaPagar::where('status', 'paga')->whereBetween('pago_em', [$ini, $fim]);

        $topServicos = OrdemServicoItem::whereIn('ordem_servico_id', $idsOs)->where('situacao', 'aprovado')->where('tipo', 'servico')
            ->selectRaw('descricao, count(*) as qtd, sum(total_centavos) as total')->groupBy('descricao')->orderByDesc('total')->limit(8)->get();
        $topPecas = OrdemServicoItem::whereIn('ordem_servico_id', $idsOs)->where('situacao', 'aprovado')->where('tipo', 'peca')
            ->selectRaw('descricao, sum(quantidade) as qtd, sum(total_centavos) as total')->groupBy('descricao')->orderByDesc('total')->limit(8)->get();

        $carros = (clone $entregues)->count();
        $faturado = (int) (clone $entregues)->sum('total_centavos');

        return view('financeiro.relatorio', [
            'mes' => $ini,
            'carros' => $carros,
            'faturado' => $faturado,
            'ticket' => $carros ? intdiv($faturado, $carros) : 0,
            'recebidoPorForma' => (clone $recebido)->selectRaw('forma_recebida, sum(valor_centavos) as total')->groupBy('forma_recebida')->pluck('total', 'forma_recebida'),
            'recebido' => (int) (clone $recebido)->sum('valor_centavos'),
            'pagoPorCategoria' => (clone $pago)->selectRaw('categoria, sum(valor_centavos) as total')->groupBy('categoria')->orderByDesc('total')->pluck('total', 'categoria'),
            'pago' => (int) (clone $pago)->sum('valor_centavos'),
            'topServicos' => $topServicos,
            'topPecas' => $topPecas,
            'custoPecas' => (int) \App\Models\EstoqueMovimento::where('origem', 'os')->whereIn('origem_id', $idsOs)->where('quantidade', '<', 0)
                ->get()->sum(fn ($m) => (int) round(abs((float) $m->quantidade) * (int) $m->custo_unitario_centavos))
                - (int) \App\Models\EstoqueMovimento::where('origem', 'os')->whereIn('origem_id', $idsOs)->where('quantidade', '>', 0)
                ->get()->sum(fn ($m) => (int) round((float) $m->quantidade * (int) $m->custo_unitario_centavos)),
            'comissoesMes' => (int) \App\Models\Comissao::whereIn('ordem_servico_id', $idsOs)->sum('valor_centavos'),
            'recusadoMes' => (int) OrdemServicoItem::whereIn('ordem_servico_id', $idsOs)->where('situacao', 'recusado')->sum('total_centavos'),
        ]);
    }
}
