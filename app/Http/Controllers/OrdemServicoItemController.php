<?php

namespace App\Http\Controllers;

use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;
use App\Models\Servico;
use App\Support\Dinheiro;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrdemServicoItemController extends Controller
{
    public function store(Request $request, OrdemServico $os)
    {
        $this->authorize('os.editar');
        if (! $os->status->aceitaItens()) {
            return back()->with('erro', 'Esta OS não aceita itens novos nesta etapa.');
        }

        $request->merge([
            'quantidade' => str_replace(',', '.', (string) ($request->input('quantidade') ?: '1')),
            'valor' => Dinheiro::paraCentavos($request->input('valor')),
        ]);
        $dados = $request->validate([
            'tipo' => ['required', Rule::in(['servico', 'peca'])],
            'servico_id' => ['nullable', Rule::exists('servicos', 'id')->where('empresa_id', Tenant::id())],
            'produto_id' => ['nullable', Rule::exists('produtos', 'id')->where('empresa_id', Tenant::id())],
            'descricao' => ['required_without_all:servico_id,produto_id', 'nullable', 'string', 'max:200'],
            'quantidade' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'valor' => ['required', 'integer', 'min:0', 'max:99999999'],
        ], [
            'descricao.required_without_all' => 'Escreva o serviço ou a peça.',
        ], ['valor' => 'valor', 'quantidade' => 'quantidade']);

        $servico = $dados['tipo'] === 'servico' && ! empty($dados['servico_id']) ? Servico::find($dados['servico_id']) : null;
        $produto = $dados['tipo'] === 'peca' && ! empty($dados['produto_id']) ? \App\Models\Produto::find($dados['produto_id']) : null;
        $descricao = trim((string) ($dados['descricao'] ?? '')) ?: ($servico?->nome ?? $produto?->nome);

        $item = $os->itens()->create([
            'tipo' => $dados['tipo'],
            'servico_id' => $servico?->id,
            'produto_id' => $produto?->id,
            'descricao' => $descricao,
            'quantidade' => $dados['quantidade'],
            'valor_unitario_centavos' => $dados['valor'] ?: ($servico?->preco_centavos ?? $produto?->preco_centavos ?? 0),
            'situacao' => 'pendente',
        ]);
        $os->recalcular();
        $os->registrar(($os->aprovado_em ? 'Item adicional: ' : 'Item incluído: ').$item->descricao);

        return back()->with('ok', $item->descricao.' incluído.')->withFragment('itens');
    }

    public function destroy(OrdemServico $os, OrdemServicoItem $item)
    {
        $this->authorize('os.editar');
        abort_unless($item->ordem_servico_id === $os->id, 404);
        if (! $os->status->aceitaItens()) {
            return back()->with('erro', 'Esta OS não pode mais ser alterada.');
        }
        if ($item->situacao === 'aprovado' && ! auth()->user()->can('os.cancelar')) {
            return back()->with('erro', 'Item já aprovado pelo cliente. Só o gerente ou o dono pode remover.');
        }

        $item->delete();
        $os->recalcular();
        $os->registrar('Item removido: '.$item->descricao);

        return back()->with('ok', 'Item removido.')->withFragment('itens');
    }
}
