<?php

namespace App\Http\Controllers;

use App\Models\AutocenterServico;
use App\Models\OrdemServico;
use App\Models\Veiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AutocenterServicoController extends Controller
{
    public function index(Veiculo $veiculo)
    {
        $this->authorize('veiculos.ver');
        return view('veiculos.autocenter', [
            'veiculo' => $veiculo,
            'registros' => AutocenterServico::where('veiculo_id', $veiculo->id)->latest('id')->paginate(30),
            'ordens' => OrdemServico::where('veiculo_id', $veiculo->id)
                ->where('status', '!=', \App\Support\StatusOs::Cancelada)
                ->orderByDesc('id')->limit(50)->get(['id', 'numero']),
            'tipos' => AutocenterServico::TIPOS,
            'posicoes' => AutocenterServico::POSICOES,
        ]);
    }

    public function store(Request $request, Veiculo $veiculo)
    {
        $this->authorize('veiculos.editar');
        $dados = $request->validate([
            'tipo' => ['required', Rule::in(AutocenterServico::TIPOS)],
            'ordem_servico_id' => ['nullable', 'integer'],
            'km_registrado' => ['nullable', 'integer', 'between:0,9999999'],
            'posicao_origem' => ['nullable', Rule::in(AutocenterServico::POSICOES)],
            'posicao_destino' => ['nullable', Rule::in(AutocenterServico::POSICOES)],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($dados['tipo'] === 'rodizio' && (empty($dados['posicao_origem']) || empty($dados['posicao_destino']) || $dados['posicao_origem'] === $dados['posicao_destino'])) {
            throw ValidationException::withMessages(['posicao_destino' => 'Rodízio exige posições de origem e destino diferentes.']);
        }
        if ($dados['tipo'] !== 'rodizio' && (!empty($dados['posicao_origem']) || !empty($dados['posicao_destino']))) {
            throw ValidationException::withMessages(['posicao_origem' => 'Posições de transferência são exclusivas de rodízio.']);
        }
        DB::transaction(function () use ($veiculo, $dados) {
            $atual = Veiculo::whereKey($veiculo->id)->lockForUpdate()->firstOrFail();
            if (!empty($dados['ordem_servico_id'])) {
                $os = OrdemServico::whereKey($dados['ordem_servico_id'])->where('veiculo_id', $atual->id)->lockForUpdate()->first();
                if (!$os) {
                    throw ValidationException::withMessages(['ordem_servico_id' => 'OS não pertence ao veículo e à oficina.']);
                }
                if ($os->trashed() || $os->status === \App\Support\StatusOs::Cancelada) {
                    throw ValidationException::withMessages(['ordem_servico_id' => 'Não é possível vincular serviço a uma OS cancelada ou excluída.']);
                }
            }
            $dados['veiculo_id'] = $atual->id;
            $dados['user_id'] = auth()->id();
            AutocenterServico::create($dados);
        });
        return redirect()->route('veiculos.autocenter.index', $veiculo)->with('ok', 'Serviço registrado no histórico do veículo.');
    }
}
