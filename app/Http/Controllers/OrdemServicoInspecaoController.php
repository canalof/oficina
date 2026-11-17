<?php

namespace App\Http\Controllers;

use App\Models\OrdemServico;
use App\Models\Lembrete;
use App\Models\OrdemServicoInspecao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrdemServicoInspecaoController extends Controller
{
    public function lembrar(Request $request, OrdemServico $os, OrdemServicoInspecao $inspecao)
    {
        $this->authorize('agenda.editar');
        abort_unless($inspecao->ordem_servico_id === $os->id, 404);
        abort_if($inspecao->gravidade === 'ok', 422, 'Selecione uma recomendação de revisão.');
        $dados = $request->validate(['data_prevista' => ['required', 'date_format:Y-m-d']]);
        abort_unless($os->cliente && $os->cliente_id === $os->veiculo?->cliente_id, 422, 'Confira o cliente atual do veículo antes de criar o lembrete.');
        $lembrete = Lembrete::firstOrCreate(['inspecao_id' => $inspecao->id], [
            'ordem_servico_id' => $os->id, 'cliente_id' => $os->cliente_id, 'veiculo_id' => $os->veiculo_id,
            'descricao' => 'Revisar '.$inspecao->componente,
            'data_prevista' => $dados['data_prevista'], 'km_previsto' => $inspecao->revisar_km,
            'status' => 'pendente',
        ]);

        return redirect()->route('lembretes.index', ['ver' => 'todos'])->with('ok', $lembrete->wasRecentlyCreated
            ? 'Recomendação registrada na fila de revisão.'
            : 'Já existe um lembrete para esta recomendação.');
    }

    public function store(Request $request, OrdemServico $os)
    {
        $this->authorize('os.executar');
        DB::transaction(function () use ($request, $os) {
            $atual = OrdemServico::whereKey($os->id)->lockForUpdate()->firstOrFail();
            abort_unless($atual->status->aceitaItens(), 403);
            $dados = $request->validate([
                'componente' => ['required', 'string', 'max:100'],
                'gravidade' => ['required', Rule::in(array_keys(OrdemServicoInspecao::GRAVIDADES))],
                'observacao' => ['nullable', 'string', 'max:1500'],
                'foto_id' => ['nullable', 'integer', Rule::exists('ordem_servico_fotos', 'id')->where('ordem_servico_id', $atual->id)],
                'item_id' => ['nullable', 'integer', Rule::exists('ordem_servico_itens', 'id')->where('ordem_servico_id', $atual->id)],
                'revisar_em' => ['nullable', 'date_format:Y-m-d'],
                'revisar_km' => ['nullable', 'integer', 'between:0,9999999'],
            ]);
            $atual->inspecoes()->create($dados);
            $atual->alterouOrcamento();
            $atual->registrar('Item de inspeção visual registrado.');
        }, 3);

        return back()->with('ok', 'Inspeção registrada e disponível no orçamento.')->withFragment('inspecao');
    }
}
