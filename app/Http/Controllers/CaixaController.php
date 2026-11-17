<?php

namespace App\Http\Controllers;

use App\Models\Caixa;
use App\Models\CaixaMovimento;
use App\Models\Empresa;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
        $troco = Dinheiro::paraCentavos($request->input('troco'));
        if ($troco < 0) {
            throw ValidationException::withMessages(['troco' => 'O troco não pode ser negativo.']);
        }
        $abriu = DB::transaction(function () use ($troco) {
            // Serializa inclusive duas primeiras aberturas sem caixa existente.
            Empresa::query()->whereKey(Tenant::id())->lockForUpdate()->firstOrFail();
            if (Caixa::aberto()) {
                return false;
            }
            Caixa::create([
                'aberto_por' => auth()->id(), 'aberto_em' => now(),
                'saldo_inicial_centavos' => $troco,
            ]);
            return true;
        });
        return back()->with($abriu ? 'ok' : 'erro', $abriu ? 'Caixa aberto.' : 'O caixa já está aberto.');
    }

    /** Suprimento (põe dinheiro) ou sangria (tira dinheiro). */
    public function movimento(Request $request)
    {
        $this->authorize('caixa.operar');
        $request->merge(['valor' => Dinheiro::paraCentavos($request->input('valor'))]);
        $dados = $request->validate([
            'tipo' => ['required', 'in:suprimento,sangria'],
            'valor' => ['required', 'integer', 'min:1'],
            'descricao' => ['required', 'string', 'max:200'],
        ], ['valor.min' => 'Informe o valor.'], ['descricao' => 'motivo']);
        $resultado = DB::transaction(function () use ($dados) {
            $caixa = Caixa::query()->whereNull('fechado_em')->latest('id')->lockForUpdate()->first();
            abort_unless($caixa, 404);
            if ($dados['tipo'] === 'sangria' && $dados['valor'] > $caixa->dinheiroEsperado()) {
                return false;
            }
            CaixaMovimento::create([
                'caixa_id' => $caixa->id, 'user_id' => auth()->id(), 'tipo' => $dados['tipo'], 'forma' => 'dinheiro',
                'valor_centavos' => $dados['valor'], 'descricao' => $dados['descricao'], 'created_at' => now(),
            ]);
            return true;
        });
        return back()->with($resultado ? 'ok' : 'erro', $resultado
            ? ($dados['tipo'] === 'sangria' ? 'Retirada' : 'Reforço').' de '.Dinheiro::formatar($dados['valor']).' registrado.'
            : 'Não há essa quantia em dinheiro no caixa.');
    }

    public function fechar(Request $request)
    {
        $this->authorize('caixa.operar');
        $request->validate(['contado' => ['required'], 'observacao' => ['nullable', 'string', 'max:1000']],
            ['contado.required' => 'Conte o dinheiro da gaveta e informe o valor.']);
        $contado = Dinheiro::paraCentavos($request->input('contado'));
        if ($contado < 0) {
            throw ValidationException::withMessages(['contado' => 'O valor contado não pode ser negativo.']);
        }
        [$caixa, $dif] = DB::transaction(function () use ($contado, $request) {
            $caixa = Caixa::query()->whereNull('fechado_em')->latest('id')->lockForUpdate()->first();
            abort_unless($caixa, 404);
            $esperado = $caixa->dinheiroEsperado();
            $caixa->update([
                'fechado_em' => now(), 'fechado_por' => auth()->id(),
                'dinheiro_esperado_centavos' => $esperado, 'dinheiro_contado_centavos' => $contado,
                'observacao' => $request->input('observacao'),
            ]);
            return [$caixa, $contado - $esperado];
        });
        return redirect()->route('caixa.show', $caixa)->with($dif === 0 ? 'ok' : 'erro', $dif === 0
            ? 'Caixa fechado. O dinheiro bateu.'
            : 'Caixa fechado com diferença de '.Dinheiro::formatar(abs($dif)).($dif > 0 ? ' a mais.' : ' a menos.'));
    }
}
