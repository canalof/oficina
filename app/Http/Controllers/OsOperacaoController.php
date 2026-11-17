<?php

namespace App\Http\Controllers;

use App\Models\CompraItem;
use App\Models\OrdemServico;
use App\Models\OsTerceirizado;
use App\Support\StatusOs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OsOperacaoController extends Controller
{
    public function pedido(OrdemServico $os, int $item)
    {
        $this->authorize('os.ver');
        $this->authorize('compras.lancar');
        $compra = $os->comprasPendentes()->where('item_id', $item)->firstOrFail();
        $peca = $os->itens()->where('tipo', 'peca')->where('situacao', 'aprovado')->findOrFail($item);
        $cotacao = $compra->cotacoes[$compra->escolhida] ?? null;
        abort_unless($cotacao, 404);

        return response()->view('os.pedido-peca', compact('os', 'peca', 'cotacao'))->header('Cache-Control', 'private, no-store');
    }

    public function compra(Request $request, OrdemServico $os)
    {
        $this->authorize('os.editar');
        $this->authorize('compras.lancar');
        $dados = $request->validate([
            'item_id' => ['required', 'integer'],
            'cotacoes' => ['required', 'array', 'list', 'max:5'],
            'cotacoes.*' => ['array:fornecedor,valor,frete,previsao'],
            'cotacoes.*.fornecedor' => ['nullable', 'string', 'max:120'],
            'cotacoes.*.valor' => ['nullable', 'integer', 'between:0,99999999'],
            'cotacoes.*.frete' => ['nullable', 'integer', 'between:0,99999999'],
            'cotacoes.*.previsao' => ['nullable', 'date_format:Y-m-d'],
            'escolhida' => ['nullable', 'integer', 'between:0,4'],
            'compra_item_id' => ['nullable', 'integer'],
        ]);
        DB::transaction(function () use ($os, $dados) {
            $atual = OrdemServico::whereKey($os->id)->lockForUpdate()->firstOrFail();
            abort_unless($atual->status->noPatio(), 409);
            $item = $atual->itens()->where('tipo', 'peca')->where('situacao', 'aprovado')->findOrFail($dados['item_id']);
            $cotacoes = [];
            foreach ($dados['cotacoes'] as $indice => $cotacao) {
                if (! filled($cotacao['fornecedor'] ?? null) && ! collect($cotacao)->contains(fn ($valor) => $valor !== null)) {
                    continue;
                }
                abort_unless(filled($cotacao['fornecedor'] ?? null) && isset($cotacao['valor']), 422, 'Informe fornecedor e valor em cada cotação preenchida.');
                $cotacoes[$indice] = $cotacao;
            }
            abort_if($cotacoes === [], 422, 'Informe pelo menos uma cotação.');
            $escolhida = $dados['escolhida'] ?? null;
            abort_if($escolhida !== null && ! isset($cotacoes[$escolhida]), 422, 'Escolha uma cotação preenchida.');
            $compraId = $dados['compra_item_id'] ?? null;
            if ($compraId !== null) {
                abort_unless($escolhida !== null && $item->produto_id && CompraItem::query()
                    ->whereKey($compraId)->where('produto_id', $item->produto_id)
                    ->whereHas('compra', fn ($q) => $q->where('status', 'lancada'))->exists(),
                    422, 'Selecione uma cotação e um item de XML lançado para esta peça.');
            }
            $atual->comprasPendentes()->updateOrCreate(['item_id' => $item->id], [
                'cotacoes' => $cotacoes, 'escolhida' => $escolhida, 'compra_item_id' => $compraId,
            ]);
        }, 3);

        return back()->with('ok', 'Compra vinculada à OS atualizada.')->withFragment('operacao-os');
    }

    public function terceirizar(Request $request, OrdemServico $os)
    {
        $this->authorize('os.editar');
        $dados = $request->validate([
            'servico' => ['required', 'string', 'max:150'],
            'parceiro' => ['required', 'string', 'max:150'],
            'responsavel' => ['required', 'string', 'max:120'],
            'custo_centavos' => ['required', 'integer', 'between:0,99999999'],
            'previsao' => ['required', 'date_format:Y-m-d'],
        ]);
        DB::transaction(function () use ($os, $dados) {
            $atual = OrdemServico::whereKey($os->id)->lockForUpdate()->firstOrFail();
            abort_unless($atual->status->noPatio(), 409);
            $atual->terceirizados()->create($dados + ['status' => 'aguardando']);
        }, 3);

        return back()->with('ok', 'Serviço terceirizado registrado.')->withFragment('operacao-os');
    }

    public function atualizarTerceirizado(Request $request, OrdemServico $os, OsTerceirizado $terceirizado)
    {
        $this->authorize('os.editar');
        abort_unless($terceirizado->ordem_servico_id === $os->id, 404);
        $dados = $request->validate([
            'status' => ['required', Rule::in(['aguardando', 'enviado', 'recebido', 'cancelado'])],
            'previsao' => ['required', 'date_format:Y-m-d'],
        ]);
        DB::transaction(function () use ($os, $terceirizado, $dados) {
            $atual = OrdemServico::whereKey($os->id)->lockForUpdate()->firstOrFail();
            abort_unless($atual->status->noPatio(), 409);
            $terceirizado->update($dados);
        }, 3);

        return back()->with('ok', 'Serviço terceirizado atualizado.')->withFragment('operacao-os');
    }

    public function trocaOleo(Request $request, OrdemServico $os)
    {
        $this->authorize('os.executar');
        $dados = $request->validate([
            'especificacao' => ['required', 'string', 'max:120'],
            'litros' => ['required', 'numeric', 'decimal:0,2', 'between:0.01,999'],
            'filtro' => ['nullable', 'string', 'max:120'],
            'km' => ['required', 'integer', 'between:0,9999999'],
            'realizada_em' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);
        DB::transaction(function () use ($os, $dados) {
            $atual = OrdemServico::whereKey($os->id)->lockForUpdate()->firstOrFail();
            abort_if($atual->status === StatusOs::Cancelada, 409);
            $atual->trocaOleo()->updateOrCreate([], $dados);
        }, 3);

        return back()->with('ok', 'Ficha de troca de óleo salva.')->withFragment('troca-oleo');
    }
}
