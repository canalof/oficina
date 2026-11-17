<?php

namespace App\Http\Controllers;

use App\Models\CnhIntegracao;
use App\Models\ConferenciaCnh;
use App\Models\MobilidadeCondutor;
use App\Models\OrdemServico;
use App\Services\Cnh\CatalogoCnh;
use App\Services\Cnh\ConsultaCnh;
use App\Services\Cnh\SenatranProvider;
use App\Support\Documento;
use App\Support\Tenant;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConferenciaCnhController extends Controller
{
    public function index(Request $request, SenatranProvider $consulta, CatalogoCnh $catalogo)
    {
        $this->authorize('os.editar');
        $filtro = $request->validate(['os' => ['sometimes', 'integer', 'min:1']]);
        $os = isset($filtro['os']) ? OrdemServico::findOrFail($filtro['os']) : null;
        $configuracao = $consulta->configuracao($request->user());
        $byok = CnhIntegracao::query()->where('provedor', 'senatran')->where('ativa', true)->first();

        return response()->view('cnh.index', [
            'os' => $os,
            'condutores' => MobilidadeCondutor::query()->orderBy('nome')->limit(500)->get(['id', 'nome']),
            'finalidadesConsulta' => $configuracao['finalidades'] ?? [],
            'modoConsulta' => match (true) {
                $configuracao !== null => 'legado',
                $byok !== null => 'byok',
                default => 'indisponivel',
            },
            'ambienteConsulta' => $catalogo->ambiente('senatran'),
            'conferencias' => ConferenciaCnh::with(['usuario:id,name', 'ordemServico:id,numero'])
                ->when($os, fn ($q) => $q->where('ordem_servico_id', $os->id))
                ->latest('id')->paginate(20)->withQueryString(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function consultar(Request $request, SenatranProvider $consulta, ConsultaCnh $orquestrador)
    {
        $this->authorize('os.editar');
        $configuracao = $consulta->configuracao($request->user());
        $integracao = $configuracao === null
            ? CnhIntegracao::query()->where('provedor', 'senatran')->where('ativa', true)->first()
            : null;
        abort_unless($configuracao !== null || $integracao !== null, 403,
            'Consulta indisponível para esta oficina. Configure e ative a integração em Integrações de CNH.');

        $finalidades = $configuracao['finalidades'] ?? (array) ($integracao?->finalidades ?? []);
        $dados = $request->validate([
            'cpf_condutor' => ['required', 'string', 'regex:/^\d{11}$/D', function ($campo, $cpf, $falhar) {
                if (! Documento::cpfValido($cpf)) {
                    $falhar('Informe um CPF válido.');
                }
            }],
            'registro_cnh' => ['required', 'string', 'regex:/^\d{11}$/D'],
            'finalidade' => ['required', Rule::in($finalidades)],
            'confirmacao' => ['accepted'],
        ], [], ['cpf_condutor' => 'CPF do condutor', 'registro_cnh' => 'registro da CNH', 'confirmacao' => 'confirmação da consulta']);

        // F-247-02: toda consulta — com ou sem condutor, BYOK ou legado — passa
        // pelo mesmo ponto e gera trilha em cnh_consultas antes da chamada.
        try {
            if ($configuracao !== null) {
                $resultado = $orquestrador->executarLegado($request->user(), 'senatran', $dados['finalidade'], [
                    'cpf' => $dados['cpf_condutor'],
                    'autenticidade' => $consulta->autenticidade(),
                ], fn () => $consulta->consultar($configuracao, $dados['cpf_condutor'], $dados['registro_cnh']));
            } else {
                $resultado = $orquestrador->executar($request->user(), 'senatran', [
                    'cpf' => $dados['cpf_condutor'], 'registro' => $dados['registro_cnh'],
                ], ['finalidade' => $dados['finalidade'], 'cpf' => $dados['cpf_condutor']]);
            }
        } catch (DomainException $erro) {
            return back()->with('erro', $erro->getMessage());
        }

        return response()->view('cnh.resultado', ['resultado' => $resultado])
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        $this->authorize('os.editar');
        $dados = $request->validate([
            'ordem_servico_id' => ['nullable', 'integer', Rule::exists('ordens_servico', 'id')->where('empresa_id', Tenant::id())->whereNull('deleted_at')],
            'condutor_id' => ['required', 'integer', Rule::exists('mobilidade_condutores', 'id')->where('empresa_id', Tenant::id())->whereNull('deleted_at')],
            'finalidade' => ['required', Rule::in(array_keys(ConferenciaCnh::FINALIDADES))],
            'metodo' => ['required', Rule::in(array_keys(ConferenciaCnh::METODOS))],
            'resultado' => ['required', Rule::in(array_keys(ConferenciaCnh::RESULTADOS))],
        ], [], [
            'condutor_id' => 'condutor cadastrado',
            'ordem_servico_id' => 'ordem de serviço',
        ]);

        $condutor = MobilidadeCondutor::findOrFail($dados['condutor_id']);

        // F-247-03: conferência manual é declaratória, nunca consulta oficial.
        // Registra a origem e a ausência de comprovação para o gate de liberação.
        $registro = ConferenciaCnh::create([
            'ordem_servico_id' => $dados['ordem_servico_id'] ?? null,
            'condutor_id' => $condutor->id,
            'condutor' => mb_substr((string) $condutor->nome, 0, 120),
            'user_id' => $request->user()->id,
            'finalidade' => $dados['finalidade'],
            'metodo' => $dados['metodo'],
            'resultado' => $dados['resultado'],
            'evidencias' => [
                'origem' => 'declarado',
                'autenticidade' => 'nao_verificada',
                'liberacao' => 'nao_liberado',
                'dados_conferidos' => false,
                'categoria_compativel' => false,
                'registrado_em' => now()->toIso8601String(),
                'observacao' => 'Conferência manual declarada pelo operador; não substitui consulta oficial.',
            ],
        ]);

        return redirect()->route('cnh.index', ['os' => $registro->ordem_servico_id])->with('ok', 'Conferência declaratória registrada. Ela não substitui a consulta oficial.');
    }
}
