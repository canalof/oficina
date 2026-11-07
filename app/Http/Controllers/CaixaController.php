<?php

namespace App\Http\Controllers;

use App\Models\Caixa;
use App\Models\CaixaMovimento;
use App\Support\Dinheiro;
use Illuminate\Http\Request;

class CaixaController extends Controller
{
    public function index()
    {
        $this->authorize('caixa.operar');
        $caixa = Caixa::aberto();

        return view('caixa.index', [
            'caixa' => $caixa?->load('abertoPor'),
            'movimentos' => $caixa?->movimentos()->with('user')->get() ?? collect(),
            'anteriores' => Caixa::whereNotNull('fechado_em')->with(['abertoPor', 'fechadoPor'])->latest('id')->limit(15)->get(),
        ]);
    }

    public function show(Caixa $caixa)
    {
        $this->authorize('financeiro.ver');

        return view('caixa.show', ['caixa' => $caixa->load(['abertoPor', 'fechadoPor']), 'movimentos' => $caixa->movimentos()->with('user')->get()]);
    }

    public function abrir(Request $request)
    {
        $this->authorize('caixa.operar');
        if (Caixa::aberto()) {
            return back()->with('erro', 'O caixa já está aberto.');
        }
        Caixa::create([
            'aberto_por' => auth()->id(), 'aberto_em' => now(),
            'saldo_inicial_centavos' => Dinheiro::paraCentavos($request->input('troco')),
        ]);

        return back()->with('ok', 'Caixa aberto.');
    }

    /** Suprimento (põe dinheiro) ou sangria (tira dinheiro). */
    public function movimento(Request $request)
    {
        $this->authorize('caixa.operar');
        $caixa = Caixa::aberto();
        abort_unless($caixa, 404);
        $request->merge(['valor' => Dinheiro::paraCentavos($request->input('valor'))]);
        $dados = $request->validate([
            'tipo' => ['required', 'in:suprimento,sangria'],
            'valor' => ['required', 'integer', 'min:1'],
            'descricao' => ['required', 'string', 'max:200'],
        ], ['valor.min' => 'Informe o valor.'], ['descricao' => 'motivo']);
        if ($dados['tipo'] === 'sangria' && $dados['valor'] > $caixa->dinheiroEsperado()) {
            return back()->with('erro', 'Não há essa quantia em dinheiro no caixa.');
        }
        CaixaMovimento::create([
            'caixa_id' => $caixa->id, 'user_id' => auth()->id(), 'tipo' => $dados['tipo'], 'forma' => 'dinheiro',
            'valor_centavos' => $dados['valor'], 'descricao' => $dados['descricao'], 'created_at' => now(),
        ]);

        return back()->with('ok', ($dados['tipo'] === 'sangria' ? 'Retirada' : 'Reforço').' de '.Dinheiro::formatar($dados['valor']).' registrado.');
    }

    public function fechar(Request $request)
    {
        $this->authorize('caixa.operar');
        $caixa = Caixa::aberto();
        abort_unless($caixa, 404);
        $request->validate(['contado' => ['required']], ['contado.required' => 'Conte o dinheiro da gaveta e informe o valor.']);
        $contado = Dinheiro::paraCentavos($request->input('contado'));
        $esperado = $caixa->dinheiroEsperado();
        $caixa->update([
            'fechado_em' => now(), 'fechado_por' => auth()->id(),
            'dinheiro_esperado_centavos' => $esperado, 'dinheiro_contado_centavos' => $contado,
            'observacao' => $request->input('observacao'),
        ]);
        $dif = $contado - $esperado;

        return redirect()->route('caixa.show', $caixa)->with($dif === 0 ? 'ok' : 'erro', $dif === 0
            ? 'Caixa fechado. O dinheiro bateu.'
            : 'Caixa fechado com diferença de '.Dinheiro::formatar(abs($dif)).($dif > 0 ? ' a mais.' : ' a menos.'));
    }
}
