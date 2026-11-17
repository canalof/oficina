<?php

namespace App\Http\Controllers;

use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;
use App\Models\Servico;
use App\Support\Dinheiro;
use App\Support\QuantidadeOs;
use App\Support\Tenant;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrdemServicoItemController extends Controller
{
    public function store(Request $request, OrdemServico $os)
    {
        $this->authorize('os.editar');
        if (! $os->status->aceitaItens()) {
            return back()->with('erro', 'Esta OS não aceita itens novos nesta etapa.');
        }

        // Campo vazio usa o valor do catálogo; zero explícito é um valor válido
        // (por exemplo, item em garantia/cortesia), não um pedido de preço padrão.
        $valorInformado = $request->exists('valor') && trim((string) $request->input('valor')) !== '';
        $request->merge([
            'quantidade' => QuantidadeOs::normalizar($request->input('quantidade'), true),
            'valor' => Dinheiro::paraCentavos($request->input('valor')),
        ]);
        $dados = $request->validate([
            'tipo' => ['required', Rule::in(['servico', 'peca'])],
            'servico_id' => ['nullable', 'prohibited_if:tipo,peca', Rule::exists('servicos', 'id')->where('empresa_id', Tenant::id())->where('ativo', true)->whereNull('deleted_at')],
            'produto_id' => ['nullable', 'prohibited_if:tipo,servico', Rule::exists('produtos', 'id')->where('empresa_id', Tenant::id())->where('ativo', true)->whereNull('deleted_at')],
            'descricao' => ['required_without_all:servico_id,produto_id', 'nullable', 'string', 'max:200'],
            'quantidade' => ['required', 'numeric', 'gt:0', 'max:99999', function ($atributo, $valor, $falhar) {
                if (! QuantidadeOs::precisaoValida($valor)) {
                    $falhar('A quantidade deve ter no máximo três casas decimais.');
                }
            }],
            'valor' => ['required', 'integer', 'min:0', 'max:99999999'],
        ], [
            'descricao.required_without_all' => 'Escreva o serviço ou a peça.',
        ], ['valor' => 'valor', 'quantidade' => 'quantidade']);

        // Não permitir item sem descrição nem referência válida de catálogo.
        $servico = $dados['tipo'] === 'servico' && ! empty($dados['servico_id']) ? Servico::find($dados['servico_id']) : null;
        $produto = $dados['tipo'] === 'peca' && ! empty($dados['produto_id']) ? \App\Models\Produto::find($dados['produto_id']) : null;
        $descricao = trim((string) ($dados['descricao'] ?? '')) ?: ($servico?->nome ?? $produto?->nome);

        if ($descricao === null || $descricao === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['descricao' => 'Informe uma descrição ou selecione um item válido do catálogo.']);
        }

        try {
            $item = DB::transaction(function () use ($os, $dados, $servico, $produto, $descricao, $valorInformado) {
                $atual = OrdemServico::whereKey($os->id)->lockForUpdate()->firstOrFail();
                if (! $atual->status->aceitaItens()) {
                    throw new DomainException('Esta OS não aceita itens novos nesta etapa.');
                }
                $item = $atual->itens()->create([
                    'tipo' => $dados['tipo'],
                    'servico_id' => $servico?->id,
                    'produto_id' => $produto?->id,
                    'descricao' => $descricao,
                    'quantidade' => $dados['quantidade'],
                    'valor_unitario_centavos' => $valorInformado ? $dados['valor'] : ($servico?->preco_centavos ?? $produto?->preco_centavos ?? 0),
                    'situacao' => 'pendente',
                ]);
                $atual->recalcular();
                $atual->alterouOrcamento();
                $atual->registrar(($atual->aprovado_em ? 'Item adicional: ' : 'Item incluído: ').$item->descricao);

                return $item;
            }, 3);
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage())->withInput();
        }
        $aviso = null;
        if ($produto && ($disp = $produto->disponivel()) < (float) $dados['quantidade']) {
            $aviso = ' Atenção: só há '.\App\Models\Produto::formatarQtd($disp).' '.$produto->unidade.' disponível no estoque.';
        }

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
            'quantidade' => QuantidadeOs::normalizar($request->input('quantidade')),
            'valor' => Dinheiro::paraCentavos($request->input('valor')),
        ]);
        $dados = $request->validate([
            'descricao' => ['required', 'string', 'max:200'],
            'quantidade' => ['required', 'numeric', 'gt:0', 'max:99999', function ($atributo, $valor, $falhar) {
                if (! QuantidadeOs::precisaoValida($valor)) {
                    $falhar('A quantidade deve ter no máximo três casas decimais.');
                }
            }],
            'valor' => ['required', 'integer', 'min:0', 'max:99999999'],
            'mecanico_id' => ['nullable', Rule::exists('users', 'id')->where('empresa_id', Tenant::id())],
        ], [], ['valor' => 'valor', 'quantidade' => 'quantidade', 'mecanico_id' => 'mecânico']);

        try {
            $resultado = DB::transaction(function () use ($os, $item, $dados) {
                $atual = OrdemServico::whereKey($os->id)->lockForUpdate()->firstOrFail();
                if (! $atual->status->aceitaItens()) {
                    throw new DomainException('Esta OS não pode mais ser alterada. Para corrigir uma OS entregue, reabra-a.');
                }
                $atualItem = $atual->itens()->whereKey($item->id)->firstOrFail();
                $antes = ['qtd' => (float) $atualItem->quantidade, 'valor' => $atualItem->valor_unitario_centavos, 'desc' => $atualItem->descricao];
                $mudouValor = round((float) $dados['quantidade'], 3) !== round((float) $atualItem->quantidade, 3) || $dados['valor'] !== $atualItem->valor_unitario_centavos;
                $mudouDescricao = $antes['desc'] !== $dados['descricao'];
                // A aprovação refere-se a um serviço específico, não apenas ao preço.
                $voltaAprovacao = ($mudouValor || $mudouDescricao) && $atualItem->situacao === 'aprovado';

                $atualItem->update([
                    'descricao' => $dados['descricao'], 'quantidade' => $dados['quantidade'], 'valor_unitario_centavos' => $dados['valor'],
                    'mecanico_id' => $dados['mecanico_id'] ?? null,
                    'situacao' => $voltaAprovacao ? 'pendente' : $atualItem->situacao,
                ]);
                $atual->recalcular();
                if ($mudouValor || $mudouDescricao) {
                    $atual->alterouOrcamento();
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
                $atual->registrar('Item corrigido: '.$atualItem->descricao.($partes ? ' ('.implode(', ', $partes).')' : '').($voltaAprovacao ? '; precisa de nova aprovação' : ''));

                return $voltaAprovacao;
            }, 3);
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage())->withInput();
        }
        $voltaAprovacao = $resultado;

        return back()->with('ok', $voltaAprovacao ? 'Item corrigido. Como o orçamento mudou, ele voltou para aprovação do cliente.' : 'Item corrigido.')->withFragment('itens');
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

        try {
            DB::transaction(function () use ($os, $item) {
                $atual = OrdemServico::whereKey($os->id)->lockForUpdate()->firstOrFail();
                if (! $atual->status->aceitaItens()) {
                    throw new DomainException('Esta OS não pode mais ser alterada.');
                }
                $atualItem = $atual->itens()->whereKey($item->id)->firstOrFail();
                if ($atualItem->situacao === 'aprovado' && ! auth()->user()->can('os.cancelar')) {
                    throw new DomainException('Item já aprovado pelo cliente. Só o gerente ou o dono pode remover.');
                }
                $descricao = $atualItem->descricao;
                $atualItem->delete();
                $atual->recalcular();
                $atual->alterouOrcamento();
                $atual->registrar('Item removido: '.$descricao);
            }, 3);
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('ok', 'Item removido.')->withFragment('itens');
    }
}
