<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\CnhConsulta;
use App\Models\CnhIntegracao;
use App\Models\ConferenciaCnh;
use App\Models\Empresa;
use App\Services\Cnh\CatalogoCnh;
use App\Services\Cnh\PoliticaRetencaoCnh;
use App\Support\Tenant;
use DomainException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CnhIntegracaoController extends Controller
{
    public function index(CatalogoCnh $catalogo, PoliticaRetencaoCnh $retencao)
    {
        $this->authorize('empresa.configurar');
        $empresa = Empresa::findOrFail(Tenant::id());

        return response()->view('cnh.integracoes', [
            'catalogo' => CatalogoCnh::FORNECEDORES,
            'implementados' => array_keys(CatalogoCnh::IMPLEMENTADOS),
            'pendentes' => CatalogoCnh::PENDENTES,
            'ambientes' => collect(array_keys(CatalogoCnh::FORNECEDORES))
                ->mapWithKeys(fn (string $codigo) => [$codigo => $catalogo->ambiente($codigo)]),
            'fontes' => collect(array_keys(CatalogoCnh::FORNECEDORES))
                ->mapWithKeys(fn (string $codigo) => [$codigo => $catalogo->fonte($codigo)]),
            'integracoes' => CnhIntegracao::query()->get()->keyBy('provedor'),
            'consultas' => CnhConsulta::latest('id')->paginate(20),
            'consumo' => CnhConsulta::where('created_at', '>=', now()->startOfMonth())->selectRaw('provedor, count(*) as quantidade')->groupBy('provedor')->pluck('quantidade', 'provedor'),
            'retencao' => $retencao->efetiva($empresa),
            'retencaoLimites' => [
                'minimo' => (int) config('cnh.retencao_limites.minimo', PoliticaRetencaoCnh::MINIMO),
                'maximo' => (int) config('cnh.retencao_limites.maximo', PoliticaRetencaoCnh::MAXIMO),
                'tecnico' => (int) config('cnh.retencao_limites.tecnico', PoliticaRetencaoCnh::TECNICO),
            ],
            'expurgoAtivo' => (bool) config('cnh.expurgo_ativo', false),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function salvar(Request $request, string $provedor, CatalogoCnh $catalogo)
    {
        $this->authorize('empresa.configurar');
        abort_unless($catalogo->existe($provedor), 404);
        $adaptador = $catalogo->adaptador($provedor);
        abort_unless($adaptador, 409, 'Este fornecedor ainda não possui conector implementado.');
        $substituindo = CnhIntegracao::query()->where('provedor', $provedor)->exists();
        $regras = [
            'credenciais' => ['required', 'array:'.implode(',', array_keys($adaptador->regras()))],
            'confirmacao_contrato' => ['accepted'],
            'finalidades' => ['required', 'array', 'min:1', 'max:2'],
            'finalidades.*' => ['required', 'distinct', Rule::in(array_keys(ConferenciaCnh::FINALIDADES))],
        ];
        foreach ($adaptador->regras() as $campo => $validacao) {
            $regras['credenciais.'.$campo] = $validacao;
        }
        // F-247-06: substituir credenciais exige reautenticação (senha atual).
        if ($substituindo) {
            $regras['senha_atual'] = ['required', 'current_password'];
        }
        $dados = $request->validate($regras, [
            'senha_atual.required' => 'Confirme sua senha para substituir as credenciais desta integração.',
            'senha_atual.current_password' => 'A senha informada não confere.',
        ]);
        $registro = null;
        DB::transaction(function () use ($request, $provedor, $dados, $substituindo, &$registro) {
            Empresa::whereKey(Tenant::id())->lockForUpdate()->firstOrFail();
            $registro = CnhIntegracao::updateOrCreate(['provedor' => $provedor], [
                'credenciais' => $dados['credenciais'] + [
                    'operador_id' => $request->user()->id,
                    'registrado_por' => $request->user()->id,
                    'ambiente' => config('cnh.ambiente', 'homologacao'),
                ],
                'finalidades' => $dados['finalidades'], 'autorizada_em' => now(),
                'ativa' => false, 'testada_em' => null, 'teste_ok' => null,
            ]);
            // Evento explícito sem nenhum segredo; o trait Auditavel também registra
            // a alteração, mas não expõe credenciais (campo oculto).
            Auditoria::create([
                'empresa_id' => Tenant::id(),
                'user_id' => $request->user()->id,
                'acao' => $substituindo ? 'substituiu_credenciais' : 'configurou_credenciais',
                'modelo' => 'CnhIntegracao',
                'modelo_id' => $registro->id,
                'antes' => $substituindo ? ['provedor' => $provedor] : null,
                'depois' => [
                    'provedor' => $provedor,
                    'finalidades' => $dados['finalidades'],
                    'ambiente' => config('cnh.ambiente', 'homologacao'),
                    'autor_id' => $request->user()->id,
                ],
                'ip' => $request->ip(),
            ]);
        }, 3);

        return back()->with('ok', 'Configuração salva e desativada. Teste antes de ativar.');
    }

    public function testar(Request $request, string $provedor, CatalogoCnh $catalogo)
    {
        $this->authorize('empresa.configurar');
        abort_unless($catalogo->existe($provedor), 404);
        $adaptador = $catalogo->adaptador($provedor);
        abort_unless($adaptador, 409, 'Este fornecedor ainda não possui conector implementado.');
        $entrada = [];
        if ($provedor === 'senatran') {
            $dados = $request->validate([
                'cpf_teste' => ['required', 'string', 'regex:/^\d{11}$/D'],
                'registro_teste' => ['required', 'string', 'regex:/^\d{11}$/D'],
                'confirmacao_teste' => ['accepted'],
            ]);
            $entrada = ['cpf' => $dados['cpf_teste'], 'registro' => $dados['registro_teste']];
        }
        $integracao = CnhIntegracao::where('provedor', $provedor)->firstOrFail();
        $ok = false;
        try {
            $adaptador->testar($integracao->credenciais, $entrada);
            $ok = true;
        } catch (DomainException|ConnectionException|GuzzleException) {
            // As exceções HTTP podem conter a URL com identificadores ou cabeçalhos.
        }
        DB::transaction(function () use ($integracao, $ok) {
            $atual = CnhIntegracao::lockForUpdate()->findOrFail($integracao->id);
            abort_unless($atual->getRawOriginal('credenciais') === $integracao->getRawOriginal('credenciais'), 409);
            $atual->fill(['testada_em' => now(), 'teste_ok' => $ok]);
            if (! $ok) {
                $atual->ativa = false;
            }
            $atual->save();
        }, 3);

        return $ok ? back()->with('ok', 'Teste concluído no escopo indicado para o fornecedor.')
            : back()->with('erro', 'Teste não confirmado. Confira credenciais, autorização e disponibilidade do fornecedor.');
    }

    public function estado(Request $request, string $provedor, CatalogoCnh $catalogo)
    {
        $this->authorize('empresa.configurar');
        abort_unless($catalogo->existe($provedor), 404);
        $dados = $request->validate(['acao' => ['required', 'in:ativar,desativar,revogar'], 'confirmacao' => ['required_if:acao,ativar', 'accepted_if:acao,ativar']]);
        $ambiente = null;
        if ($dados['acao'] === 'ativar') {
            abort_unless($catalogo->implementado($provedor), 409, 'Este fornecedor ainda não possui conector implementado.');
            $ambiente = $catalogo->ambiente($provedor);
            abort_unless($ambiente['configurado'], 409, (string) $ambiente['mensagem']);
        }
        // F-247-06: revogar credenciais também exige reautenticação.
        if ($dados['acao'] === 'revogar') {
            $request->validate(['senha_atual' => ['required', 'current_password']], [
                'senha_atual.required' => 'Confirme sua senha para revogar as credenciais desta integração.',
                'senha_atual.current_password' => 'A senha informada não confere.',
            ]);
        }
        DB::transaction(function () use ($request, $provedor, $dados) {
            $integracao = CnhIntegracao::where('provedor', $provedor)->lockForUpdate()->firstOrFail();
            if ($dados['acao'] === 'revogar') {
                $antes = ['provedor' => $provedor, 'finalidades' => $integracao->finalidades, 'ativa' => (bool) $integracao->ativa];
                $integracao->delete();
                Auditoria::create([
                    'empresa_id' => Tenant::id(),
                    'user_id' => $request->user()->id,
                    'acao' => 'revogou_credenciais',
                    'modelo' => 'CnhIntegracao',
                    'modelo_id' => $integracao->id,
                    'antes' => $antes,
                    'depois' => null,
                    'ip' => $request->ip(),
                ]);

                return;
            }
            abort_if($dados['acao'] === 'ativar' && ! $integracao->teste_ok, 409, 'É necessário concluir o teste antes de ativar.');
            $integracao->update(['ativa' => $dados['acao'] === 'ativar']);
        }, 3);

        $mensagem = $ambiente !== null
            ? 'Integração ativada em '.($ambiente['ambiente'] === 'producao' ? 'produção' : 'homologação').'.'
            : 'Estado da integração atualizado.';

        return back()->with('ok', $mensagem);
    }

    /**
     * F-247-04: política de retenção por empresa. O expurgo continua desligado
     * nesta etapa; a tela apenas registra e documenta o prazo aprovado.
     */
    public function salvarRetencao(Request $request, PoliticaRetencaoCnh $politica)
    {
        $this->authorize('empresa.configurar');
        $minimo = (int) config('cnh.retencao_limites.minimo', PoliticaRetencaoCnh::MINIMO);
        $maximo = (int) config('cnh.retencao_limites.maximo', PoliticaRetencaoCnh::MAXIMO);
        $dados = $request->validate([
            'cnh_retencao_dias' => ['nullable', 'integer', 'min:'.$minimo, 'max:'.$maximo],
        ], [
            'cnh_retencao_dias.integer' => 'Informe o prazo em dias inteiros.',
            'cnh_retencao_dias.min' => "O prazo mínimo aceito é {$minimo} dias. Zero não significa retenção ilimitada.",
            'cnh_retencao_dias.max' => "O prazo máximo aceito é {$maximo} dias.",
        ]);

        $empresa = Empresa::findOrFail(Tenant::id());
        $antes = $empresa->cnh_retencao_dias;
        $empresa->cnh_retencao_dias = $dados['cnh_retencao_dias'] ?? null;
        $empresa->save();

        $efetiva = $politica->efetiva($empresa);
        Auditoria::create([
            'empresa_id' => Tenant::id(),
            'user_id' => $request->user()->id,
            'acao' => 'configurou_retencao_cnh',
            'modelo' => 'Empresa',
            'modelo_id' => $empresa->id,
            'antes' => ['cnh_retencao_dias' => $antes],
            'depois' => ['cnh_retencao_dias' => $empresa->cnh_retencao_dias, 'efetiva_dias' => $efetiva['dias'], 'origem' => $efetiva['origem']],
            'ip' => $request->ip(),
        ]);

        return back()->with('ok', 'Política de retenção atualizada. O expurgo permanece desativado nesta etapa.');
    }
}
