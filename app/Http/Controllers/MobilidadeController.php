<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\CnhConsulta;
use App\Models\CnhIntegracao;
use App\Models\ConferenciaCnh;
use App\Models\MobilidadeCondutor;
use App\Models\MobilidadeFoto;
use App\Models\MobilidadeRetirada;
use App\Models\OrdemServico;
use App\Services\Cnh\VerificationService;
use App\Support\Documento;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MobilidadeController extends Controller
{
    /** Condutores e retirada de veículo da oficina. A Locação fica em LocacaoController. */
    public function index(Request $request)
    {
        $this->authorize('mobilidade.ver');
        $filtros = $request->validate(['os' => ['sometimes', 'integer', 'min:1'], 'cliente' => ['sometimes', 'integer', 'min:1']]);
        $os = isset($filtros['os']) ? OrdemServico::findOrFail($filtros['os']) : null;
        $cliente = isset($filtros['cliente']) ? Cliente::findOrFail($filtros['cliente']) : null;
        $ordens = OrdemServico::when($cliente, fn ($q) => $q->where('cliente_id', $cliente->id));

        $retiradas = MobilidadeRetirada::when($os, fn ($q) => $q->where('ordem_servico_id', $os->id))
            ->when($cliente, fn ($q) => $q->whereIn('ordem_servico_id', (clone $ordens)->select('id')))
            ->latest('id')->limit(20)->get();

        return response()->view('mobilidade.index', [
            'os' => $os, 'cliente' => $cliente,
            'condutores' => MobilidadeCondutor::orderBy('nome')->get(['id', 'nome', 'categoria', 'validade']),
            'retiradas' => $retiradas,
            'fotosRetirada' => MobilidadeFoto::whereIn('retirada_id', $retiradas->modelKeys())->get()->groupBy('retirada_id'),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function condutor(Request $request, ?MobilidadeCondutor $condutor = null)
    {
        $this->authorize('mobilidade.editar');
        $filtros = $request->validate(['os' => ['sometimes', 'integer', 'min:1']]);
        $os = isset($filtros['os']) ? OrdemServico::findOrFail($filtros['os']) : null;
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'cpf' => ['nullable', 'string', 'regex:/^\d{11}$/D', function ($campo, $valor, $falhar) {
                if (! Documento::cpfValido($valor)) {
                    $falhar('Informe um CPF válido.');
                }
            }],
            'registro_cnh' => ['nullable', 'string', 'regex:/^\d{11}$/D'],
            'contato' => ['nullable', 'string', 'max:120'],
            'categoria' => ['nullable', Rule::in(['ACC', 'A', 'B', 'C', 'D', 'E', 'AB', 'AC', 'AD', 'AE'])],
            'validade' => ['nullable', 'date_format:Y-m-d'],
        ]);
        if ($condutor?->exists) {
            $condutor->update($dados);
        } else {
            $condutor = MobilidadeCondutor::create($dados);
        }

        return redirect()->route('mobilidade.condutor.show', ['condutor' => $condutor, 'os' => $os?->id])->with('ok', 'Condutor salvo.');
    }

    public function mostrarCondutor(Request $request, MobilidadeCondutor $condutor)
    {
        $this->authorize('mobilidade.ver');

        $filtros = $request->validate(['os' => ['sometimes', 'integer', 'min:1']]);
        $os = isset($filtros['os']) ? OrdemServico::findOrFail($filtros['os']) : null;

        return response()->view('mobilidade.condutor', [
            'condutor' => $condutor,
            'os' => $os,
            'consultasByok' => CnhConsulta::where('condutor_id', $condutor->id)->latest('id')->paginate(10, ['*'], 'consultas')->withQueryString(),
            'provedoresByok' => CnhIntegracao::where('ativa', true)->get()
                ->filter(fn (CnhIntegracao $i) => app(\App\Services\Cnh\CatalogoCnh::class)->implementado($i->provedor)
                    && (array) $i->finalidades !== []),
            'conferencias' => ConferenciaCnh::where('condutor_id', $condutor->id)->latest('id')->paginate(20),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function verificar(Request $request, MobilidadeCondutor $condutor, VerificationService $verificacao)
    {
        $this->authorize('mobilidade.editar');
        $dados = $request->validate([
            'ordem_servico_id' => ['nullable', $this->existe('ordens_servico', true)],
            'finalidade' => ['required', Rule::in(array_keys(ConferenciaCnh::FINALIDADES))],
            'resultado' => ['required', Rule::in(array_keys(ConferenciaCnh::RESULTADOS))],
            'dados_conferidos' => ['sometimes', 'boolean'],
            'categoria_compativel' => ['sometimes', 'boolean'],
        ]);
        ConferenciaCnh::create([
            'ordem_servico_id' => $dados['ordem_servico_id'] ?? null,
            'condutor_id' => $condutor->id, 'condutor' => $condutor->nome,
            'user_id' => $request->user()->id, 'finalidade' => $dados['finalidade'],
            'metodo' => 'documento', 'resultado' => $dados['resultado'],
            'evidencias' => $verificacao->evidencias($condutor, $request->boolean('dados_conferidos'), $request->boolean('categoria_compativel')),
        ]);

        return back()->with('ok', 'Conferência assistida registrada.');
    }

    public function retirada(Request $request, OrdemServico $os)
    {
        $this->authorize('mobilidade.editar');
        $dados = $request->validate([
            'condutor_id' => ['required', $this->existe('mobilidade_condutores', true)],
            'terceiro' => ['sometimes', 'boolean'],
            'autorizacao' => ['required_if:terceiro,1', 'nullable', 'string', 'max:2000'],
            'aceite_nome' => ['required', 'string', 'max:120'],
            'checklist' => ['nullable', 'string', 'max:4000'],
            'justificativa' => ['nullable', 'string', 'min:15', 'max:1000'],
        ]);
        if (! empty($dados['justificativa'])) {
            $this->authorize('mobilidade.excecao');
        }
        DB::transaction(function () use ($os, $dados, $request) {
            $atual = OrdemServico::whereKey($os->id)->lockForUpdate()->firstOrFail();
            abort_unless($atual->status->noPatio(), 409, 'Esta OS já está encerrada.');
            MobilidadeRetirada::create($dados + ['ordem_servico_id' => $atual->id, 'user_id' => $request->user()->id]);
            $atual->registrar(empty($dados['justificativa']) ? 'Autorização de retirada registrada.' : 'Exceção de retirada registrada no módulo Mobilidade.');
        }, 3);

        return back()->with('ok', 'Retirada preparada. A entrega é registrada pela OS.');
    }

    public function politica(Request $request)
    {
        $this->authorize('mobilidade.excecao');
        $empresa = $request->user()->empresa;
        $empresa->forceFill([
            'mobilidade_exige_cnh' => $request->boolean('exige_cnh'),
            'mobilidade_exige_oficial' => $request->boolean('exige_oficial'),
            'mobilidade_bloqueia_divergencia' => $request->boolean('bloqueia_divergencia'),
        ])->save();

        return back()->with('ok', 'Política de liberação atualizada.');
    }

    private function existe(string $tabela, bool $softDelete = false)
    {
        $regra = Rule::exists($tabela, 'id')->where('empresa_id', Tenant::id());

        return $softDelete ? $regra->whereNull('deleted_at') : $regra;
    }
}
