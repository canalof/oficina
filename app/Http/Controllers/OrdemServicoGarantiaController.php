<?php

namespace App\Http\Controllers;

use App\Models\OrdemServico;
use App\Models\OrdemServicoGarantia;
use App\Models\Empresa;
use App\Support\StatusOs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrdemServicoGarantiaController extends Controller
{
    public function retorno(OrdemServico $os, OrdemServicoGarantia $garantia)
    {
        $this->authorize('os.editar');
        abort_unless($garantia->ordem_servico_id === $os->id, 404);
        $retorno = DB::transaction(function () use ($os, $garantia) {
            Empresa::whereKey($os->empresa_id)->lockForUpdate()->firstOrFail();
            $atual = OrdemServicoGarantia::whereKey($garantia->id)->lockForUpdate()->firstOrFail();
            if ($atual->retorno_id) {
                return OrdemServico::findOrFail($atual->retorno_id);
            }
            abort_unless($os->status === StatusOs::Entregue && $os->veiculo && $os->veiculo->cliente_id === $os->cliente_id, 409, 'Confira a OS original e o proprietário atual do veículo.');
            $retorno = OrdemServico::abrir([
                'veiculo_id' => $os->veiculo_id, 'cliente_id' => $os->cliente_id, 'consultor_id' => auth()->id(),
                'defeito_relatado' => 'Retorno em garantia da OS '.$os->numeroFormatado().' — '.$atual->peca,
            ]);
            $atual->forceFill(['retorno_id' => $retorno->id])->save();
            $retorno->registrar('Retorno em garantia vinculado à OS '.$os->numeroFormatado().'.');

            return $retorno;
        }, 3);

        return redirect()->route('os.show', $retorno)->with('ok', 'Registre cortesia com valor zero. Itens cobrados seguem a aprovação do orçamento.');
    }

    public function store(Request $request, OrdemServico $os)
    {
        $this->authorize('os.editar');
        abort_unless($os->status === StatusOs::Entregue, 409, 'Registre a ocorrência na OS original entregue.');
        $dados = $request->validate([
            'peca' => ['required', 'string', 'max:150'],
            'fornecedor' => ['nullable', 'string', 'max:150'],
            'prazo' => ['nullable', 'date_format:Y-m-d'],
            'relato' => ['required', 'string', 'max:2000'],
            'foto_id' => ['nullable', 'integer', Rule::exists('ordem_servico_fotos', 'id')->where('ordem_servico_id', $os->id)],
        ]);
        $os->garantias()->create($dados + ['status' => 'aberta']);

        return back()->with('ok', 'Ocorrência de garantia registrada.')->withFragment('garantias');
    }

    public function update(Request $request, OrdemServico $os, OrdemServicoGarantia $garantia)
    {
        $this->authorize('os.editar');
        abort_unless($garantia->ordem_servico_id === $os->id, 404);
        $dados = $request->validate([
            'status' => ['required', Rule::in(array_keys(OrdemServicoGarantia::STATUS))],
            'resolucao' => ['required_if:status,troca,credito,recusada', 'nullable', 'string', 'max:2000'],
            'custo_pecas_centavos' => ['nullable', 'integer', 'between:0,99999999'],
            'custo_mao_obra_centavos' => ['nullable', 'integer', 'between:0,99999999'],
        ]);
        $garantia->update($dados);

        return back()->with('ok', 'Ocorrência atualizada.')->withFragment('garantias');
    }
}
