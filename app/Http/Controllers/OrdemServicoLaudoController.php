<?php

namespace App\Http\Controllers;

use App\Models\OrdemServico;
use App\Models\OrdemServicoLaudo;
use App\Support\StatusOs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrdemServicoLaudoController extends Controller
{
    public function update(Request $request, OrdemServico $os)
    {
        $this->authorize('os.executar');
        $regras = [
            'medicoes' => ['nullable', 'array:'.implode(',', array_keys(OrdemServicoLaudo::RODAS))],
            'medicoes.*' => ['array:'.implode(',', array_keys(OrdemServicoLaudo::CAMPOS))],
            'recomendacoes' => ['nullable', 'string', 'max:2000'],
            'publicado' => ['sometimes', 'boolean'],
        ];
        foreach (OrdemServicoLaudo::CAMPOS as $campo => $rotulo) {
            $regras['medicoes.*.'.$campo] = ['nullable', 'numeric', 'decimal:0,2', $campo === 'sulco' ? 'between:0,30' : 'between:-90,90'];
        }
        $dados = $request->validate($regras);
        $medicoes = [];
        foreach ($dados['medicoes'] ?? [] as $roda => $campos) {
            $valores = array_filter($campos, fn ($valor) => $valor !== null);
            if ($valores !== []) {
                $medicoes[$roda] = array_map('floatval', $valores);
            }
        }
        if ($medicoes === [] && empty($dados['recomendacoes'])) {
            throw ValidationException::withMessages(['medicoes' => 'Informe pelo menos uma medida ou uma recomendação.']);
        }
        DB::transaction(function () use ($os, $dados, $medicoes, $request) {
            $atual = OrdemServico::whereKey($os->id)->lockForUpdate()->firstOrFail();
            abort_if($atual->status === StatusOs::Cancelada, 409);
            $atual->laudo()->updateOrCreate([], [
                'medicoes' => $medicoes,
                'recomendacoes' => $dados['recomendacoes'] ?? null,
                'publicado' => $request->boolean('publicado'),
            ]);
        }, 3);

        return back()->with('ok', 'Laudo salvo.')->withFragment('laudo-alinhamento');
    }
}
