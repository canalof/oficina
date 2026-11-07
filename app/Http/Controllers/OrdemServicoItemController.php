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
        $os->alterouOrcamento();
        $aviso = null;
        if ($produto && ($disp = $produto->disponivel()) < (float) $dados['quantidade']) {
            $aviso = ' Atenção: só há '.\App\Models\Produto::formatarQtd($disp).' '.$produto->unidade.' disponível no estoque.';
        }
        $os->registrar(($os->aprovado_em ? 'Item adicional: ' : 'Item incluído: ').$item->descricao);

        return back()->with('ok', $item->descricao.' incluído.'.($aviso ?? ''))->withFragment('itens');
    }

    /** Corrige descrição, quantidade, valor ou mecânico de um item já lançado. */
    public function update(Request $request, OrdemServico $os, OrdemServicoItem $item)
    {
        $this->authorize('os.editar');
        abort_unless($item->ordem_servico_id === $os->id, 404);
        if (! $os->status->aceitaItens()) {
            return back()->with('erro', 'Esta OS não pode mais ser alterada. Para corrigir uma OS entregue, reabra-a.');
        }
        $request->merge([
            'quantidade' => str_replace(',', '.', (string) $request->input('quantidade')),
            'valor' => Dinheiro::paraCentavos($request->input('valor')),
        ]);
        $dados = $request->validate([
            'descricao' => ['required', 'string', 'max:200'],
            'quantidade' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'valor' => ['required', 'integer', 'min:0', 'max:99999999'],
            'mecanico_id' => ['nullable', Rule::exists('users', 'id')->where('empresa_id', Tenant::id())],
        ], [], ['valor' => 'valor', 'quantidade' => 'quantidade', 'mecanico_id' => 'mecânico']);

        $antes = ['qtd' => (float) $item->quantidade, 'valor' => $item->valor_unitario_centavos, 'desc' => $item->descricao];
        $mudouValor = round((float) $dados['quantidade'], 3) !== round((float) $item->quantidade, 3) || $dados['valor'] !== $item->valor_unitario_centavos;
        $voltaAprovacao = $mudouValor && $item->situacao === 'aprovado';

        $item->update([
            'descricao' => $dados['descricao'], 'quantidade' => $dados['quantidade'], 'valor_unitario_centavos' => $dados['valor'],
            'mecanico_id' => $dados['mecanico_id'] ?? null,
            'situacao' => $voltaAprovacao ? 'pendente' : $item->situacao,
        ]);
        $os->recalcular();
        if ($mudouValor || $antes['desc'] !== $dados['descricao']) {
            $os->alterouOrcamento();
        }
        $partes = [];
        if ($antes['desc'] !== $dados['descricao']) {
            $partes[] = 'descrição "'.$antes['desc'].'"';
        }
        if ($antes['qtd'] != (float) $dados['quantidade']) {
            $partes[] = 'qtd '.\App\Models\Produto::formatarQtd($antes['qtd']).' → '.\App\Models\Produto::formatarQtd($dados['quantidade']);
        }
        if ($antes['valor'] !== $dados['valor']) {
            $partes[] = 'valor '.Dinheiro::formatar($antes['valor']).' → '.Dinheiro::formatar($dados['valor']);
        }
        $os->registrar('Item corrigido: '.$item->descricao.($partes ? ' ('.implode(', ', $partes).')' : '').($voltaAprovacao ? '; precisa de nova aprovação' : ''));

        return back()->with('ok', $voltaAprovacao ? 'Item corrigido. Como o valor mudou, ele voltou para aprovação do cliente.' : 'Item corrigido.')->withFragment('itens');
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
        $os->alterouOrcamento();
        $os->registrar('Item removido: '.$item->descricao);

        return back()->with('ok', 'Item removido.')->withFragment('itens');
    }
}
