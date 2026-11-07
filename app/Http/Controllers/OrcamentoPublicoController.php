<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\OrdemServico;
use App\Models\OrdemServicoFoto;
use App\Support\StatusOs;
use App\Support\Tenant;
use DomainException;
use Illuminate\Http\Request;

/**
 * Página que o cliente abre pelo link do WhatsApp para ver e aprovar o orçamento.
 * Não exige login: o link tem um código aleatório de 40 caracteres.
 */
class OrcamentoPublicoController extends Controller
{
    public function ver(string $token)
    {
        $os = $this->os($token);
        if (! $os->linkValido()) {
            return response()->view('orcamento.expirado', ['empresa' => Empresa::find($os->empresa_id)], 410)->header('X-Robots-Tag', 'noindex, nofollow');
        }
        $os->load(['veiculo', 'cliente', 'itens', 'fotos']);

        return response()->view('orcamento.ver', [
            'os' => $os,
            'empresa' => Empresa::find($os->empresa_id),
        ])->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }

    public function aprovar(Request $request, string $token)
    {
        $os = $this->os($token);
        if (! $os->linkValido()) {
            return redirect()->route('orcamento.ver', $token);
        }
        $dados = $request->validate([
            'versao' => ['required', 'integer'],
            'nome' => ['required', 'string', 'min:2', 'max:120'],
            'itens' => ['nullable', 'array'],
            'itens.*' => ['integer'],
            'decisao' => ['required', 'in:aprovar,recusar'],
        ], ['nome.required' => 'Escreva seu nome para confirmar.'], ['nome' => 'nome']);

        $ids = $dados['decisao'] === 'recusar' ? [] : array_map('intval', $dados['itens'] ?? []);
        if ($dados['decisao'] === 'aprovar' && ! $ids) {
            return back()->withErrors(['itens' => 'Marque pelo menos um item para aprovar, ou use "Recusar orçamento".'])->withInput();
        }

        try {
            $os->aprovar($ids, trim($dados['nome']).' (pelo link)', $request->ip(), 'link', $request->userAgent(), (int) $dados['versao']);
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return redirect()->route('orcamento.ver', $token)->with('ok', $ids ? 'Obrigado! Sua aprovação foi registrada e já aparece para a oficina.' : 'Tudo bem, registramos que você não aprovou. A oficina vai entrar em contato.');
    }

    public function foto(string $token, int $foto)
    {
        $os = $this->os($token); // define a empresa antes de buscar a foto
        abort_unless($os->linkValido(), 404);

        return OrdemServicoFotoController::enviar($os, OrdemServicoFoto::findOrFail($foto));
    }

    private function os(string $token): OrdemServico
    {
        $os = OrdemServico::porToken($token);
        abort_if(! $os, 404);
        abort_unless(Tenant::id() === $os->empresa_id, 404);

        return $os;
    }
}
