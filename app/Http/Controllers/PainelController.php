<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\OrdemServico;
use App\Support\StatusOs;
use App\Models\PlacaConsulta;
use App\Models\Veiculo;

class PainelController extends Controller
{
    public function __invoke()
    {
        $inicioMes = now()->startOfMonth();

        return view('painel', [
            'totalClientes' => Cliente::count(),
            'clientesMes' => Cliente::where('created_at', '>=', $inicioMes)->count(),
            'totalVeiculos' => Veiculo::count(),
            'veiculosMes' => Veiculo::where('created_at', '>=', $inicioMes)->count(),
            'consultasMes' => PlacaConsulta::where('created_at', '>=', $inicioMes)->where('custo_centavos', '>', 0)->count(),
            'gastoPlacaMes' => PlacaConsulta::where('created_at', '>=', $inicioMes)->sum('custo_centavos'),
            'ultimosVeiculos' => Veiculo::with('cliente')->latest()->limit(6)->get(),
            'porStatus' => OrdemServico::whereIn('status', array_map(fn ($s) => $s->value, StatusOs::doPatio()))
                ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'entreguesMes' => OrdemServico::where('status', StatusOs::Entregue->value)->where('entregue_em', '>=', $inicioMes)->count(),
            'valorEntreguesMes' => (int) OrdemServico::where('status', StatusOs::Entregue->value)->where('entregue_em', '>=', $inicioMes)->sum('total_centavos'),
            'atrasadas' => OrdemServico::with(['veiculo', 'cliente'])
                ->whereIn('status', [StatusOs::Aprovada->value, StatusOs::EmServico->value, StatusOs::Orcamento->value, StatusOs::Aguardando->value])
                ->whereNotNull('previsao_entrega')->where('previsao_entrega', '<', now())->orderBy('previsao_entrega')->limit(5)->get(),
        ]);
    }
}
