<?php

namespace App\Http\Controllers;

use App\Models\PneuInspecao;
use App\Models\Veiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PneuInspecaoController extends Controller
{
    public function index(Veiculo $veiculo)
    {
        $this->authorize('veiculos.ver');
        return view('veiculos.pneus', [
            'veiculo' => $veiculo,
            'inspecoes' => PneuInspecao::where('veiculo_id', $veiculo->id)->latest('id')->paginate(30),
            'posicoes' => PneuInspecao::POSICOES,
        ]);
    }

    public function store(Request $request, Veiculo $veiculo)
    {
        $this->authorize('veiculos.editar');
        $dados = $request->validate([
            'posicao' => ['required', Rule::in(PneuInspecao::POSICOES)],
            'medida' => ['nullable', 'string', 'max:30'],
            'marca' => ['nullable', 'string', 'max:80'],
            'dot' => ['nullable', 'regex:/^[0-9]{4}$/'],
            'sulco_decimos_mm' => ['nullable', 'integer', 'between:0,300'],
            'km_registrado' => ['nullable', 'integer', 'between:0,9999999'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ]);
        if (! empty($dados['dot'])) {
            $semana = (int) substr($dados['dot'], 0, 2);
            $ano = 2000 + (int) substr($dados['dot'], 2, 2);
            // A semana 53 não existe em todos os anos. Rejeitar também
            // semanas de fabricação ainda não alcançadas no ano corrente.
            $inicio = (new \DateTimeImmutable('now'))->setISODate($ano, $semana, 1)->setTime(0, 0);
            $ultimaSemana = (int) (new \DateTimeImmutable($ano.'-12-28'))->format('W');
            if ($semana < 1 || $semana > $ultimaSemana || $inicio > new \DateTimeImmutable('now')) {
                throw ValidationException::withMessages(['dot' => 'DOT inválido: semana inexistente ou data de fabricação futura.']);
            }
        }
        DB::transaction(function () use ($veiculo, $dados) {
            $atual = Veiculo::whereKey($veiculo->id)->lockForUpdate()->firstOrFail();
            $dados['veiculo_id'] = $atual->id;
            $dados['user_id'] = auth()->id();
            PneuInspecao::create($dados);
        });
        return redirect()->route('veiculos.pneus.index', $veiculo)->with('ok', 'Inspeção registrada no histórico.');
    }
}
