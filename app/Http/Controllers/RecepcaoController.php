<?php

namespace App\Http\Controllers;

use App\Models\Veiculo;
use App\Services\Placa\ConsultaPlaca;
use App\Services\Placa\ConsultaPlacaIndisponivel;
use App\Support\Placa;
use Illuminate\Http\Request;

/**
 * Recepção: o carro chega, a placa é lida ou digitada e a tela mostra
 * a ficha do veículo (se já cadastrado) ou os dados vindos da consulta (se novo).
 */
class RecepcaoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('veiculos.ver');

        return view('recepcao.index', ['placa' => Placa::normalizar($request->query('placa'))]);
    }

    public function buscar(Request $request, ConsultaPlaca $consulta)
    {
        $this->authorize('veiculos.ver');
        $placa = Placa::normalizar($request->query('placa'));

        if (! Placa::valida($placa)) {
            return response()->json(['status' => 'invalida', 'mensagem' => 'Placa inválida. Use o formato ABC1234 ou ABC1D23.'], 422);
        }

        $veiculo = Veiculo::porPlaca($placa);
        if ($veiculo) {
            $veiculo->load(['cliente', 'donos.cliente']);

            return response()->json([
                'status' => 'cadastrado',
                'html' => view('recepcao._ficha', ['veiculo' => $veiculo])->render(),
            ]);
        }

        if (! $request->user()->can('placa.consultar')) {
            return response()->json([
                'status' => 'novo',
                'html' => view('recepcao._novo', ['placa' => $placa, 'dados' => null, 'aviso' => null])->render(),
            ]);
        }

        try {
            $r = $consulta->buscar($placa);
            $aviso = $r['dados'] ? null : 'A consulta não encontrou dados desta placa. Preencha manualmente.';
            if ($r['dados']?->rouboFurto) {
                $aviso = 'Atenção: o serviço de consulta indica registro de roubo ou furto para esta placa.';
            }
        } catch (ConsultaPlacaIndisponivel $e) {
            $r = ['dados' => null, 'origem' => 'erro'];
            $aviso = $e->getMessage().' Preencha os dados manualmente.';
        }

        return response()->json([
            'status' => 'novo',
            'html' => view('recepcao._novo', ['placa' => $placa, 'dados' => $r['dados'], 'aviso' => $aviso])->render(),
        ]);
    }
}
