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
            'cobrancaManual' => $this->cobrancaManual(),
            'usuarios' => $empresa->usuarios()->where('ativo', true)->count(),
        ]);
    }

    public function assinar(Request $request, Assinaturas $assinaturas)
    {
        $this->authorize('empresa.configurar');
        if ($this->cobrancaManual()) {
            return back()->with('erro', 'Solicite a cobrança ao administrador do TORQUE.');
        }
        $plano = Plano::where('slug', $request->input('plano'))->where('ativo', true)->firstOrFail();
        try {
            $assinaturas->assinar($request->user()->empresa, $plano, $request->user()->email);
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }
        $empresa = $request->user()->empresa->fresh();

        return back()->with('ok', $empresa->assinatura_status === 'ativa'
            ? 'Plano '.$plano->nome.' ativo.'
            : 'Assinatura do plano '.$plano->nome.' criada. Consulte a fatura nesta tela em instantes.');
    }

    private function cobrancaManual(): bool
    {
        return app()->isProduction() && config('cobranca.provedor') === 'demonstracao';
    }
}
