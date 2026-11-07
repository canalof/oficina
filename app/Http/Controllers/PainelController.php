<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
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
        ]);
    }
}
