<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\AgendaSolicitacao;
use App\Models\Agendamento;
use App\Models\Empresa;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Portal do cliente: link pessoal (sem senha) com os carros, as OS, garantias e próximas revisões.
 * A oficina gera, envia e pode revogar o link a qualquer momento.
 */
class PortalClienteController extends Controller
{
    public function ver(string $token)
    {
        $cliente = $this->clienteDoToken($token);
        $cliente->load(['veiculos.ordens' => fn ($q) => $q
            ->where('cliente_id', $cliente->id)
            ->whereNotIn('status', ['cancelada'])->with(['itens', 'laudo' => fn ($laudo) => $laudo->where('publicado', true)])->limit(20)]);

        return response()->view('portal.ver', [
            'cliente' => $cliente,
            'empresa' => Empresa::find($cliente->empresa_id),
            'solicitacoes' => AgendaSolicitacao::with(['veiculo', 'agendamento'])->where('cliente_id', $cliente->id)
                ->whereHas('veiculo', fn ($q) => $q->where('cliente_id', $cliente->id))->latest('id')->limit(10)->get(),
            'lembretes' => \App\Models\Lembrete::where('cliente_id', $cliente->id)->where('status', '<>', 'descartado')->where('data_prevista', '>=', today()->subMonth())->orderBy('data_prevista')->get(),
        ])->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function solicitar(Request $request, string $token)
    {
        $cliente = $this->clienteDoToken($token);
        $dados = $request->validate([
            'veiculo_id' => ['required', 'integer'],
            'preferencia' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'],
            'servico' => ['required', 'string', 'max:200'],
        ]);
        DB::transaction(function () use ($cliente, $dados, $token) {
            Cliente::whereKey($cliente->id)->where('portal_token', $token)->lockForUpdate()->firstOrFail();
            $veiculo = $cliente->veiculos()->lockForUpdate()->findOrFail($dados['veiculo_id']);
            $aberta = AgendaSolicitacao::where('veiculo_id', $veiculo->id)->where('cliente_id', $cliente->id)
                ->where(fn ($q) => $q->where('status', 'pendente')->orWhere(fn ($q) => $q->where('status', 'proposta')
                    ->whereHas('agendamento', fn ($q) => $q->where('status', 'proposto')->where('inicio', '>', now()))))->exists();
            if (! $aberta) {
                AgendaSolicitacao::create($dados + ['cliente_id' => $cliente->id, 'status' => 'pendente']);
            }
        }, 3);

        return redirect()->route('portal.ver', $token)->with('ok', 'A oficina pode confirmar ou oferecer outro horário. Confira a situação nesta página.');
    }

    public function responderProposta(Request $request, string $token, int $solicitacao)
    {
        $cliente = $this->clienteDoToken($token);
        $dados = $request->validate(['decisao' => ['required', 'in:aceitar,recusar']]);
        DB::transaction(function () use ($cliente, $token, $solicitacao, $dados) {
            Empresa::whereKey($cliente->empresa_id)->lockForUpdate()->firstOrFail();
            Cliente::whereKey($cliente->id)->where('portal_token', $token)->lockForUpdate()->firstOrFail();
            $pedido = AgendaSolicitacao::where('cliente_id', $cliente->id)->lockForUpdate()->findOrFail($solicitacao);
            $cliente->veiculos()->lockForUpdate()->findOrFail($pedido->veiculo_id);
            $agenda = Agendamento::whereKey($pedido->agendamento_id)->lockForUpdate()->firstOrFail();
            abort_unless($pedido->status === 'proposta' && $agenda->status === 'proposto' && $agenda->inicio->isFuture(), 409, 'A proposta não está mais disponível.');
            $aceita = $dados['decisao'] === 'aceitar';
            $agenda->update(['status' => $aceita ? 'confirmado' : 'cancelado']);
            $pedido->update(['status' => $aceita ? 'confirmada' : 'recusada']);
        }, 3);

        return redirect()->route('portal.ver', $token)->with('ok', 'Resposta registrada.');
    }

    private function clienteDoToken(string $token): Cliente
    {
        abort_if(strlen($token) < 32, 404);
        // Busca fora da empresa atual só pelo token (aleatório e único); depois fixa a empresa
        $cliente = Cliente::withoutGlobalScope('empresa')->where('portal_token', $token)->first();
        abort_unless($cliente, 404);
        Tenant::usar($cliente->empresa_id);

        return $cliente;
    }

    public function gerar(Cliente $cliente)
    {
        $this->authorize('clientes.editar');
        $cliente->forceFill(['portal_token' => Str::random(40)])->save();

        return back()->with('ok', 'Link do portal gerado. Os links anteriores deixaram de funcionar.');
    }

    public function revogar(Cliente $cliente)
    {
        $this->authorize('clientes.editar');
        $cliente->forceFill(['portal_token' => null])->save();

        return back()->with('ok', 'Link do portal desativado.');
    }
}
