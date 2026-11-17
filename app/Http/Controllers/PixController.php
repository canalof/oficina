<?php

namespace App\Http\Controllers;

use App\Models\CobrancaPix;
use App\Models\ContaBancaria;
use App\Models\ContaReceber;
use App\Services\Financeiro\Financeiro;
use App\Services\Pix\PixEstatico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Preparação de cobranças e confirmação de recebimentos Pix manuais. */
class PixController extends Controller
{
    public function index()
    {
        $this->authorize('financeiro.ver');
        $cobrancas = CobrancaPix::with('contaReceber')->latest('id')->limit(100)->get();
        $contas = ContaBancaria::where('ativa', true)->get();
        $titulos = ContaReceber::where('status', 'aberta')->limit(100)->get();
        return response()->view('pix.index', compact('cobrancas', 'contas', 'titulos'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        $this->authorize('financeiro.editar');
        $dados = $request->validate([
            'conta_bancaria_id' => ['required', 'integer'],
            'conta_receber_id' => ['required', 'integer'],
        ]);
        $manual = DB::transaction(function () use ($dados) {
            $banco = ContaBancaria::whereKey($dados['conta_bancaria_id'])->lockForUpdate()->firstOrFail();
            abort_unless($banco->ativa, 422, 'Conta bancária inativa.');
            $titulo = ContaReceber::whereKey($dados['conta_receber_id'])->lockForUpdate()->firstOrFail();
            abort_unless($titulo->status === 'aberta', 422, 'Título não está aberto.');
            abort_unless((int) $titulo->valor_centavos > 0, 422, 'Valor inválido.');
            abort_if($banco->pix_chave !== null && $titulo->valor_centavos > 999999999999, 422, 'Valor acima do limite do QR Code Pix.');
            abort_if(CobrancaPix::where('conta_receber_id', $titulo->id)->whereIn('status', ['pendente', 'confirmado'])->exists(), 409, 'Já existe cobrança ativa para o título.');
            $payload = $banco->pix_chave === null ? null : PixEstatico::gerar(
                $banco->pix_chave, $banco->pix_recebedor, $banco->pix_cidade,
                $titulo->valor_centavos, Str::random(20),
            );
            CobrancaPix::create([
                'conta_bancaria_id' => $banco->id,
                'conta_receber_id' => $titulo->id,
                'provedor' => $payload ? 'pix_manual' : 'aguardando_integracao',
                'pix_payload' => $payload,
                'valor_centavos' => $titulo->valor_centavos,
                'status' => 'pendente',
            ]);
            return $payload !== null;
        }, 3);
        return redirect()->route('pix.index')->with('ok', $manual
            ? 'QR Code e Pix Copia e Cola gerados. Confirme somente após conferir o crédito no banco.'
            : 'Rascunho preparado. Configure a chave em Bancos para gerar o QR Code.');
    }

    public function confirmarManual(CobrancaPix $cobranca, Financeiro $financeiro)
    {
        $this->authorize('caixa.operar');
        abort_unless($cobranca->provedor === 'pix_manual', 409);
        DB::transaction(function () use ($cobranca, $financeiro) {
            $titulo = ContaReceber::whereKey($cobranca->conta_receber_id)->lockForUpdate()->firstOrFail();
            $registro = CobrancaPix::whereKey($cobranca->id)->lockForUpdate()->firstOrFail();
            $registro->setRelation('contaReceber', $titulo);
            abort_unless($registro->pixManualDisponivel(), 409, 'Cobrança encerrada ou título alterado.');
            $financeiro->receberConta($titulo, 'pix');
            $registro->update(['status' => 'confirmado', 'confirmado_em' => now()]);
        }, 3);

        return redirect()->route('pix.index')->with('ok', 'Recebimento Pix confirmado manualmente.');
    }

    /** Cancela somente uma preparação local, sem comunicação com banco. */
    public function cancelarRascunho(CobrancaPix $cobranca)
    {
        $this->authorize('financeiro.editar');
        DB::transaction(function () use ($cobranca) {
            $registro = CobrancaPix::whereKey($cobranca->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                in_array($registro->provedor, ['aguardando_integracao', 'pix_manual'], true)
                && $registro->status === 'pendente'
                && $registro->id_externo === null
                && $registro->txid === null
                && $registro->confirmado_em === null,
                409,
                'Somente preparações locais sem emissão bancária podem ser canceladas aqui.'
            );
            $registro->update(['status' => 'cancelada']);
        });
        return redirect()->route('pix.index')->with('ok', 'Preparação local cancelada. Nenhuma operação bancária foi realizada.');
    }

}
