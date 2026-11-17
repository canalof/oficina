<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\FaturaPlataforma;
use App\Models\Plano;
use App\Models\WebhookRecebido;
use App\Support\Dinheiro;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Painel do dono da plataforma (você). Mostra assinaturas e uso agregado.
 * Não dá acesso aos dados internos das oficinas (clientes, OS, financeiro).
 */
class PlataformaController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $empresas = Empresa::with('plano')->withCount(['usuarios' => fn ($q) => $q->where('ativo', true)])
            ->when($status, fn ($q) => $q->where('assinatura_status', $status))
            ->when($request->query('busca'), fn ($q, $b) => $q->where('nome_fantasia', 'like', "%{$b}%"))
            ->orderByDesc('id')->paginate(40)->withQueryString();

        $ativas = Empresa::where('assinatura_status', 'ativa')->with('plano')->get();

        return view('plataforma.index', [
            'empresas' => $empresas,
            'status' => $status,
            'mrr' => (int) $ativas->sum(fn ($e) => $e->plano?->preco_centavos ?? 0),
            'contagem' => Empresa::selectRaw('assinatura_status, count(*) as n')->groupBy('assinatura_status')->pluck('n', 'assinatura_status'),
            'webhooksComErro' => WebhookRecebido::whereNotNull('erro')->where('created_at', '>', now()->subDays(7))->count(),
        ]);
    }

    public function empresa(Empresa $empresa)
    {
        return view('plataforma.empresa', [
            'empresa' => $empresa->load('plano'),
            'planos' => Plano::orderBy('ordem')->get(),
            'faturas' => Tenant::com($empresa->id, fn () => FaturaPlataforma::latest('vencimento')->limit(24)->get()),
            'uso' => Tenant::com($empresa->id, fn () => [
                'usuarios' => $empresa->usuarios()->where('ativo', true)->count(),
                'os_mes' => \App\Models\OrdemServico::where('created_at', '>=', now()->startOfMonth())->count(),
                'placas_mes' => \App\Models\PlacaConsulta::where('created_at', '>=', now()->startOfMonth())->where('custo_centavos', '>', 0)->count(),
                'notas_mes' => \App\Models\NotaFiscal::where('created_at', '>=', now()->startOfMonth())->count(),
            ]),
        ]);
    }

    public function atualizarDados(Request $request, Empresa $empresa)
    {
        $dados = $request->validate([
            'nome_fantasia' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
        ]);
        $empresa->update($dados);
        \Illuminate\Support\Facades\Log::info('plataforma: dados da oficina alterados', [
            'empresa' => $empresa->id, 'por' => $request->user()->id,
        ]);

        return back()->with('ok', 'Dados da oficina atualizados.');
    }

    public function atualizarEmpresa(Request $request, Empresa $empresa)
    {
        abort_if($empresa->assinatura_status === Empresa::CADASTRO_PENDENTE, 409, 'Libere o cadastro antes de editar a assinatura.');
        $dados = $request->validate([
            'plano_id' => ['required', Rule::exists('planos', 'id')],
            'assinatura_status' => ['required', Rule::in(array_keys(Empresa::STATUS))],
            'trial_ate' => ['nullable', 'date'],
            'ativo' => ['boolean'],
        ]);
        $dados['ativo'] = $request->boolean('ativo');
        if ($dados['assinatura_status'] === 'suspensa' && $empresa->assinatura_status !== 'suspensa') {
            $dados['suspensa_em'] = now();
        }
        if ($dados['assinatura_status'] !== 'suspensa') {
            $dados['suspensa_em'] = null;
        }
        $empresa->update($dados);
        \Illuminate\Support\Facades\Log::info('plataforma: empresa alterada', ['empresa' => $empresa->id, 'por' => $request->user()->id, 'status' => $dados['assinatura_status']]);

        return back()->with('ok', 'Empresa atualizada.');
    }

    public function liberarCadastro(Request $request, Empresa $empresa)
    {
        $liberado = Empresa::whereKey($empresa->id)
            ->where('assinatura_status', Empresa::CADASTRO_PENDENTE)
            ->update(['ativo' => true, 'assinatura_status' => 'trial', 'trial_ate' => today()->addDays(Empresa::DIAS_TRIAL)]);
        abort_unless($liberado, 409, 'Este cadastro já foi processado.');

        \Illuminate\Support\Facades\Log::info('plataforma: cadastro liberado', [
            'empresa' => $empresa->id, 'por' => $request->user()->id,
        ]);

        return back()->with('ok', 'Oficina liberada. O período de teste começa hoje.');
    }

    public function registrarCobranca(Request $request, Empresa $empresa)
    {
        abort_if($empresa->assinatura_status === Empresa::CADASTRO_PENDENTE, 409, 'Libere o cadastro da oficina antes de registrar a cobrança.');
        $request->merge(['valor_centavos' => Dinheiro::paraCentavos($request->input('valor'))]);
        $dados = $request->validate([
            'valor_centavos' => ['required', 'integer', 'min:1', 'max:99999999'],
            'vencimento' => ['required', 'date_format:Y-m-d'],
            'url' => ['required', 'string', 'max:500', 'url:https'],
        ], [], ['url' => 'link de pagamento', 'valor_centavos' => 'valor']);

        DB::transaction(function () use ($empresa, $dados) {
            Empresa::whereKey($empresa->id)->lockForUpdate()->firstOrFail();
            Tenant::com($empresa->id, function () use ($dados) {
                if (FaturaPlataforma::where('url', $dados['url'])->exists()) {
                    throw ValidationException::withMessages(['url' => 'Já existe uma cobrança com esse link nesta oficina.']);
                }
                FaturaPlataforma::create($dados + ['externo_id' => 'manual-'.Str::uuid(), 'status' => 'pendente']);
            });
        }, 3);

        return back()->with('ok', 'Cobrança registrada. Compartilhe o link com a oficina.');
    }

    public function confirmarCobranca(Empresa $empresa, int $fatura)
    {
        Tenant::com($empresa->id, function () use ($fatura) {
            $registro = FaturaPlataforma::whereKey($fatura)->where('externo_id', 'like', 'manual-%')->firstOrFail();
            $atualizado = FaturaPlataforma::whereKey($registro->id)->whereIn('status', ['pendente', 'vencida'])
                ->update(['status' => 'paga', 'paga_em' => now()]);
            abort_unless($atualizado, 409, 'Esta cobrança já foi processada.');
        });

        return back()->with('ok', 'Pagamento registrado. Para alterar o acesso da oficina, use a seção Assinatura.');
    }

    public function planos()
    {
        return view('plataforma.planos', ['planos' => Plano::orderBy('ordem')->get()]);
    }

    public function salvarPlano(Request $request, ?Plano $plano = null)
    {
        $request->merge(['preco_centavos' => Dinheiro::paraCentavos($request->input('preco'))]);
        $nulo = fn ($k) => $request->input($k) === '' || $request->input($k) === null ? null : (int) $request->input($k);
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:60'],
            'slug' => ['required', 'alpha_dash', 'max:30', Rule::unique('planos', 'slug')->ignore($plano?->id)],
            'preco_centavos' => ['integer', 'min:0'],
            'ordem' => ['nullable', 'integer'],
        ]);
        $dados += [
            'limite_usuarios' => $nulo('limite_usuarios'), 'limite_lojas' => $nulo('limite_lojas'), 'limite_consultas_placa' => $nulo('limite_consultas_placa'),
            'whatsapp_automatico' => $request->boolean('whatsapp_automatico'), 'ativo' => $request->boolean('ativo', true),
        ];
        $plano ? $plano->update($dados) : Plano::create($dados);

        return back()->with('ok', 'Plano salvo. A mudança de preço vale para assinaturas novas; as atuais seguem o valor do gateway até a troca de plano.');
    }

    public function webhooks()
    {
        return view('plataforma.webhooks', ['webhooks' => WebhookRecebido::latest('id')->paginate(50)]);
    }
}
