<?php

namespace App\Http\Controllers;

use App\Models\FaturaPlataforma;
use App\Models\Plano;
use App\Services\Cobranca\Assinaturas;
use DomainException;
use Illuminate\Http\Request;

class AssinaturaController extends Controller
{
    public function index(Request $request)
    {
        $empresa = $request->user()->empresa->load('plano');

        return view('assinatura.index', [
            'empresa' => $empresa,
            'planos' => Plano::where('ativo', true)->orderBy('ordem')->get(),
            'faturas' => FaturaPlataforma::latest('vencimento')->limit(24)->get(),
            'pode' => $request->user()->can('empresa.configurar'),
            'usuarios' => $empresa->usuarios()->where('ativo', true)->count(),
        ]);
    }

    public function assinar(Request $request, Assinaturas $assinaturas)
    {
        $this->authorize('empresa.configurar');
        $plano = Plano::where('slug', $request->input('plano'))->where('ativo', true)->firstOrFail();
        try {
            $assinaturas->assinar($request->user()->empresa, $plano, $request->user()->email);
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }
        $empresa = $request->user()->empresa->fresh();

        return back()->with('ok', $empresa->assinatura_status === 'ativa'
            ? 'Plano '.$plano->nome.' ativo.'
            : 'Assinatura do plano '.$plano->nome.' criada. A fatura aparece aqui em instantes e também chega no seu e-mail.');
    }
}
