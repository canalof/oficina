<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\PlacaConsulta;
use App\Models\Veiculo;
use App\Models\VeiculoDono;
use App\Services\Placa\DadosVeiculo;
use App\Support\Placa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VeiculoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('veiculos.ver');
        $busca = $request->query('busca');

        return view('veiculos.index', [
            'veiculos' => Veiculo::busca($busca)->with('cliente')->orderBy('placa')->paginate(20)->withQueryString(),
            'busca' => $busca,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('veiculos.editar');
        $placa = Placa::normalizar($request->query('placa'));
        $veiculo = new Veiculo(['placa' => $placa, 'cliente_id' => $request->query('cliente_id')]);

        // Preenche com a última consulta da placa, se houver (feita na recepção)
        if ($placa && ($c = PlacaConsulta::where('placa', $placa)->where('status', 'encontrado')->latest('id')->first())) {
            $veiculo->fill(DadosVeiculo::fromArray($c->resposta)->paraVeiculo());
        }

        return view('veiculos.form', ['veiculo' => $veiculo, 'clientes' => $this->clientes()]);
    }

    public function store(Request $request)
    {
        $this->authorize('veiculos.editar');
        $dados = $this->validar($request);

        $veiculo = DB::transaction(function () use ($dados) {
            $v = Veiculo::create($dados);
            if ($v->cliente_id) {
                VeiculoDono::create(['veiculo_id' => $v->id, 'cliente_id' => $v->cliente_id, 'inicio' => now()->toDateString()]);
            }

            return $v;
        });

        return redirect()->route('veiculos.show', $veiculo)->with('ok', 'Veículo '.$veiculo->placaFormatada().' cadastrado.');
    }

    public function show(Veiculo $veiculo)
    {
        $this->authorize('veiculos.ver');
        $veiculo->load(['cliente', 'donos.cliente']);

        return view('veiculos.show', ['veiculo' => $veiculo, 'clientes' => $this->clientes()]);
    }

    public function edit(Veiculo $veiculo)
    {
        $this->authorize('veiculos.editar');

        return view('veiculos.form', ['veiculo' => $veiculo, 'clientes' => $this->clientes()]);
    }

    public function update(Request $request, Veiculo $veiculo)
    {
        $this->authorize('veiculos.editar');
        $dados = $this->validar($request, $veiculo);
        $novoDono = $dados['cliente_id'] ?? null;
        unset($dados['cliente_id']);

        DB::transaction(function () use ($veiculo, $dados, $novoDono) {
            $veiculo->update($dados);
            if ((int) $novoDono !== (int) $veiculo->cliente_id) {
                $veiculo->transferirPara($novoDono ? Cliente::findOrFail($novoDono) : null);
            }
        });

        return redirect()->route('veiculos.show', $veiculo)->with('ok', 'Veículo atualizado.');
    }

    public function transferir(Request $request, Veiculo $veiculo)
    {
        $this->authorize('veiculos.editar');
        $dados = $request->validate([
            'cliente_id' => ['required', Rule::exists('clientes', 'id')->where('empresa_id', \App\Support\Tenant::id())->whereNull('deleted_at')],
        ], [], ['cliente_id' => 'novo dono']);

        $novo = Cliente::findOrFail($dados['cliente_id']);
        if ($novo->id === $veiculo->cliente_id) {
            return back()->with('erro', 'Este cliente já é o dono do veículo.');
        }
        $veiculo->transferirPara($novo);

        return back()->with('ok', 'Veículo transferido para '.$novo->nome.'. O histórico do dono anterior foi mantido.');
    }

    public function destroy(Veiculo $veiculo)
    {
        $this->authorize('veiculos.excluir');
        $veiculo->delete();

        return redirect()->route('veiculos.index')->with('ok', 'Veículo excluído.');
    }

    private function clientes()
    {
        return Cliente::orderBy('nome')->get(['id', 'nome', 'cpf_cnpj']);
    }

    private function validar(Request $request, ?Veiculo $veiculo = null): array
    {
        $request->merge([
            'placa' => Placa::normalizar($request->input('placa')),
            'chassi' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $request->input('chassi'))) ?: null,
            'km_atual' => preg_replace('/\D/', '', (string) $request->input('km_atual')) ?: null,
        ]);

        $dados = $request->validate([
            'placa' => [
                'required',
                function ($attr, $valor, $fail) {
                    if (! Placa::valida($valor)) {
                        $fail('Placa inválida. Use o formato ABC1234 ou ABC1D23.');
                    }
                },
                function ($attr, $valor, $fail) use ($veiculo) {
                    $existe = Veiculo::whereIn('placa', Placa::equivalentes($valor))
                        ->when($veiculo, fn ($q) => $q->where('id', '<>', $veiculo->id))->first();
                    if ($existe) {
                        $fail('A placa '.$existe->placaFormatada().' já está cadastrada.');
                    }
                },
            ],
            'cliente_id' => ['nullable', Rule::exists('clientes', 'id')->where('empresa_id', \App\Support\Tenant::id())->whereNull('deleted_at')],
            'marca' => ['nullable', 'string', 'max:60'],
            'modelo' => ['required', 'string', 'max:80'],
            'versao' => ['nullable', 'string', 'max:120'],
            'ano_fabricacao' => ['nullable', 'integer', 'between:1950,'.(now()->year + 1)],
            'ano_modelo' => ['nullable', 'integer', 'between:1950,'.(now()->year + 2)],
            'cor' => ['nullable', 'string', 'max:30'],
            'combustivel' => ['nullable', 'string', 'max:30'],
            'chassi' => ['nullable', 'string', 'size:17'],
            'renavam' => ['nullable', function ($a, $v, $fail) {
                if ($v && ! \App\Support\Renavam::valido($v)) {
                    $fail('Renavam inválido. Confira os 11 números do documento do carro.');
                }
            }],
            'motor' => ['nullable', 'string', 'max:40'],
            'km_atual' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'fipe_codigo' => ['nullable', 'string', 'max:12'],
            'fipe_valor_centavos' => ['nullable', 'integer', 'min:0'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ], [], ['cliente_id' => 'cliente', 'ano_fabricacao' => 'ano de fabricação', 'ano_modelo' => 'ano do modelo', 'km_atual' => 'km atual']);

        return $dados;
    }
}
