<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Support\Tenant;
use Illuminate\Support\Str;

/**
 * Portal do cliente: link pessoal (sem senha) com os carros, as OS, garantias e próximas revisões.
 * A oficina gera, envia e pode revogar o link a qualquer momento.
 */
class PortalClienteController extends Controller
{
    public function ver(string $token)
    {
        abort_if(strlen($token) < 32, 404);
        // Busca fora da empresa atual só pelo token (aleatório e único); depois fixa a empresa
        $cliente = Cliente::withoutGlobalScope('empresa')->where('portal_token', $token)->first();
        abort_unless($cliente, 404);
        Tenant::usar($cliente->empresa_id);

        $cliente->load(['veiculos.ordens' => fn ($q) => $q->whereNotIn('status', ['cancelada'])->with('itens')->limit(20)]);

        return response()->view('portal.ver', [
            'cliente' => $cliente,
            'empresa' => Empresa::find($cliente->empresa_id),
            'lembretes' => \App\Models\Lembrete::where('cliente_id', $cliente->id)->where('status', '<>', 'descartado')->where('data_prevista', '>=', today()->subMonth())->orderBy('data_prevista')->get(),
        ])->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
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
