<?php

namespace App\Http\Controllers;

use App\Models\OrdemServico;
use App\Models\ModeloOs;
use App\Models\Servico;
use App\Models\User;
use App\Models\Veiculo;
use App\Support\Checklist;
use App\Support\Dinheiro;
use App\Support\Perfil;
use App\Support\StatusOs;
use App\Support\Tenant;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrdemServicoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('os.ver');
        $visao = $request->query('visao') === 'todas' ? 'todas' : 'patio';
        $usuarioId = $request->user()->id;
        $ordens = OrdemServico::with(['veiculo', 'cliente'])->withCount([
            'mensagens as mensagens_novas' => fn ($q) => $q
                ->where(fn ($autores) => $autores->where('user_id', '<>', $usuarioId)->orWhereNull('user_id'))
                ->whereRaw('os_mensagens.id > COALESCE((SELECT ultima_mensagem_id FROM os_conversa_leituras WHERE ordem_servico_id = os_mensagens.ordem_servico_id AND user_id = ?), 0)', [$usuarioId]),
        ]);

        if ($visao === 'patio') {
            $abertas = $ordens->with('mecanico')
                ->whereIn('status', array_map(fn ($s) => $s->value, StatusOs::doPatio()))
                ->orderBy('numero')->get()
                ->groupBy(fn ($os) => $os->status->value);

            return view('os.index', ['visao' => $visao, 'colunas' => $abertas]);
        }

        $status = StatusOs::tryFrom((string) $request->query('status'));
        $busca = $request->query('busca');

        return view('os.index', [
            'visao' => $visao,
            'busca' => $busca,
            'status' => $status,
            'ordens' => $ordens
                ->busca($busca)
                ->when($status, fn ($q) => $q->where('status', $status->value))
                ->orderByDesc('numero')->paginate(25)->withQueryString(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('os.editar');
        $veiculo = Veiculo::with('cliente')->findOrFail($request->query('veiculo_id'));
        $aberta = $veiculo->ordens()->whereIn('status', array_map(fn ($s) => $s->value, StatusOs::doPatio()))->first();

        return view('os.form', [
            'os' => new OrdemServico([
                'veiculo_id' => $veiculo->id,
                'cliente_id' => $veiculo->cliente_id,
                'km_entrada' => $veiculo->km_atual,
                'consultor_id' => auth()->id(),
                'garantia_dias' => 90,
            ]),
            'veiculo' => $veiculo,
            'aberta' => $aberta,
            'mecanicos' => $this->mecanicos(),
            'clientes' => $this->clientes(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('os.editar');
        $dados = $this->validar($request, true);
        try {
            $os = OrdemServico::abrir($dados);
        } catch (DomainException $e) {
            return back()->withErrors(['cliente_id' => $e->getMessage()])->withInput();
        }

        return redirect()->route('os.show', $os)->with('ok', 'OS '.$os->numeroFormatado().' aberta. Agora adicione os serviços e peças do orçamento.');
    }

    public function show(OrdemServico $os)
    {
        $this->authorize('os.ver');
        $os->load(['veiculo', 'cliente', 'consultor', 'mecanico', 'itens.mecanico', 'itens.produto', 'fotos', 'inspecoes', 'garantias', 'laudo', 'comprasPendentes', 'terceirizados', 'trocaOleo', 'eventos.user', 'contasReceber', 'notas', 'aceites.user']);
        $mensagens = $os->mensagens()->with('autor:id,name')->latest('id')->limit(100)->get();
        if ($ultima = $mensagens->first()) {
            $leitura = ['ordem_servico_id' => $os->id, 'user_id' => auth()->id()];
            DB::table('os_conversa_leituras')->insertOrIgnore($leitura + ['ultima_mensagem_id' => $ultima->id]);
            DB::table('os_conversa_leituras')->where($leitura)->where('ultima_mensagem_id', '<', $ultima->id)
                ->update(['ultima_mensagem_id' => $ultima->id]);
        }

        return view('os.show', [
            'os' => $os,
            'consultasCnh' => auth()->user()->can('mobilidade.ver')
                ? \App\Models\CnhConsulta::where('ordem_servico_id', $os->id)->latest('id')->limit(10)->get()
                : collect(),
            'comprasPorProduto' => auth()->user()->can('compras.lancar')
                ? \App\Models\CompraItem::with('compra')->whereIn('produto_id', $os->itens->where('situacao', 'aprovado')->pluck('produto_id')->filter())
                    ->whereHas('compra', fn ($q) => $q->where('status', 'lancada'))->latest('id')->limit(150)->get()->groupBy('produto_id')
                : collect(),
            'oleoAnterior' => $os->veiculo->ordens()->whereKeyNot($os->id)->where('status', StatusOs::Entregue)
                ->whereHas('trocaOleo')->with('trocaOleo')->first()?->trocaOleo,
            'mensagens' => $mensagens->reverse(),
            'produtos' => \App\Models\Produto::where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'codigo', 'preco_centavos', 'estoque', 'unidade']),
            'caixaAberto' => \App\Models\Caixa::aberto() !== null,
            'servicos' => Servico::with('categoria')->where('ativo', true)->orderBy('nome')->get(),
            'modelosOs' => $os->status->aceitaItens() && auth()->user()->can('os.editar')
                ? ModeloOs::where('ativo', true)->orderBy('nome')->get(['id', 'nome'])
                : collect(),
            'mecanicos' => $this->mecanicos(),
            'outras' => $os->veiculo->ordens()->where('id', '<>', $os->id)->with(['itens', 'inspecoes'])->limit(10)->get(),
        ]);
    }

    public function edit(OrdemServico $os)
    {
        $this->authorize('os.editar');
        abort_unless($os->status->noPatio(), 403, 'OS encerrada não pode ser alterada.');
        $os->load(['veiculo.cliente', 'cliente']);

        return view('os.form', ['os' => $os, 'veiculo' => $os->veiculo, 'aberta' => null, 'mecanicos' => $this->mecanicos(), 'clientes' => $this->clientes()]);
    }

    public function update(Request $request, OrdemServico $os)
    {
        $this->authorize('os.editar');
        $dados = $this->validar($request, false);
        // O status verificado no route binding pode estar desatualizado.
        // Edição, mudança de etapa e aprovação devem serializar na mesma OS.
        DB::transaction(function () use ($os, $dados) {
            $atual = OrdemServico::whereKey($os->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($atual->status->noPatio(), 403, 'OS encerrada não pode ser alterada.');
            $atual->update($dados);
            if ($atual->km_entrada !== null) {
                $veiculo = Veiculo::whereKey($atual->veiculo_id)->lockForUpdate()->first();
                if ($veiculo && $atual->km_entrada > (int) $veiculo->km_atual) {
                    $veiculo->update(['km_atual' => $atual->km_entrada]);
                }
            }
            $atual->recalcular();
        }, 3);

        return redirect()->route('os.show', $os)->with('ok', 'OS atualizada.');
    }

    /** Diagnóstico e mecânico responsável (o mecânico também pode preencher). */
    public function diagnostico(Request $request, OrdemServico $os)
    {
        $this->authorize('os.executar');
        $dados = $request->validate([
            'diagnostico' => ['nullable', 'string', 'max:5000'],
            'mecanico_id' => ['nullable', Rule::exists('users', 'id')->where('empresa_id', Tenant::id())],
        ], [], ['mecanico_id' => 'mecânico']);
        DB::transaction(function () use ($os, $dados) {
            $atual = OrdemServico::whereKey($os->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($atual->status->noPatio(), 403, 'OS encerrada não pode ser alterada.');
            $atual->update($dados);
        }, 3);

        return back()->with('ok', 'Diagnóstico salvo.');
    }

    public function desconto(Request $request, OrdemServico $os)
    {
        $this->authorize('os.editar');
        $novoDesconto = max(0, Dinheiro::paraCentavos($request->input('desconto')));
        try {
            // Mesmo bloqueio usado no recebimento: uma alteração de desconto
            // concorrente não pode mudar o total de uma OS já quitada/faturada.
            DB::transaction(function () use ($os, $novoDesconto) {
                $atual = OrdemServico::whereKey($os->id)->lockForUpdate()->firstOrFail();
                abort_unless($atual->status->aceitaItens() || $atual->status === StatusOs::Pronta, 403);
                if ($atual->recebido_em !== null || $atual->contasReceber()->exists()) {
                    throw new DomainException('Não é possível alterar o desconto após registrar cobrança ou recebimento da OS.');
                }
                $atual->desconto_centavos = $novoDesconto;
                $atual->recalcular();
                $atual->alterouOrcamento();
                $atual->registrar('Desconto: '.Dinheiro::formatar($atual->desconto_centavos));
            }, 3);
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('ok', 'Desconto aplicado.');
    }

    /** Mudanças de etapa: enviar, aprovar, iniciar, concluir, entregar, cancelar. */
    public function acao(Request $request, OrdemServico $os, string $acao)
    {
        $permissao = match ($acao) {
            'enviar', 'aprovar', 'entregar', 'novo-link' => 'os.editar',
            'reabrir' => 'os.cancelar',
            'iniciar', 'concluir' => 'os.executar',
            'cancelar' => 'os.cancelar',
            default => abort(404),
        };
        $this->authorize($permissao);

        try {
            switch ($acao) {
                case 'enviar':
                    $os->marcarEnviado();
                    $link = $os->cliente?->linkWhatsapp($this->mensagemWhatsapp($os));
                    if ($request->boolean('whatsapp') && $link) {
                        return redirect()->away($link);
                    }

                    return back()->with('ok', 'Orçamento marcado como enviado. Link para o cliente: '.$os->linkAprovacao());
                case 'aprovar':
                    $ids = array_map('intval', (array) $request->input('itens', []));
                    if (! $ids) {
                        return back()->with('erro', 'Marque os itens que o cliente aprovou.');
                    }
                    $os->aprovar($ids, auth()->user()->name.' (no balcão/telefone)', $request->ip(), 'balcao', $request->userAgent());

                    return back()->with('ok', 'Aprovação registrada.');
                case 'iniciar':
                    $os->iniciar();

                    return back()->with('ok', 'Serviço iniciado.');
                case 'concluir':
                    $os->concluir();

                    return back()->with('ok', 'Carro marcado como pronto. Avise o cliente pelo WhatsApp.');
                case 'entregar':
                    $os->entregar();

                    return back()->with('ok', 'Carro entregue. Garantia até '.$os->garantiaAte()?->format('d/m/Y').'.');
                case 'novo-link':
                    $os->novoLink();

                    return back()->with('ok', 'Link novo gerado. O anterior não funciona mais.');
                case 'reabrir':
                    $motivo = trim((string) $request->input('motivo'));
                    if ($motivo === '') {
                        return back()->with('erro', 'Informe o motivo da reabertura.');
                    }
                    $os->reabrir($motivo);

                    return back()->with('ok', 'OS reaberta. Peças devolvidas ao estoque e comissões não pagas retiradas.');
                case 'cancelar':
                    $motivo = trim((string) $request->input('motivo'));
                    if ($motivo === '') {
                        return back()->with('erro', 'Informe o motivo do cancelamento.');
                    }
                    $os->cancelar($motivo);

                    return back()->with('ok', 'OS cancelada.');
            }
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }
    }

    public function imprimir(OrdemServico $os)
    {
        $this->authorize('os.ver');
        $os->load(['veiculo', 'cliente', 'consultor', 'mecanico', 'itens']);

        return view('os.imprimir', ['os' => $os, 'empresa' => auth()->user()->empresa, 'loja' => auth()->user()->loja]);
    }

    // ---------- apoio ----------

    private function mensagemWhatsapp(OrdemServico $os): string
    {
        $v = $os->veiculo;

        return 'Olá, '.$this->primeiroNome($os).'! Segue o orçamento do seu '.trim($v->descricao()).' ('.$v->placaFormatada().'): '
            .$os->totalFormatado().".\n\nVeja os itens e aprove por aqui: ".$os->linkAprovacao()."\n\n".$this->empresa();
    }

    private function primeiroNome(OrdemServico $os): string
    {
        return $os->cliente ? explode(' ', trim($os->cliente->nome))[0] : '';
    }

    private function empresa(): string
    {
        return auth()->user()->empresa->nome_fantasia;
    }

    private function clientes()
    {
        return \App\Models\Cliente::orderBy('nome')->get(['id', 'nome']);
    }

    private function mecanicos()
    {
        return User::daEmpresaAtual()->where('ativo', true)
            ->whereIn('perfil', [Perfil::Mecanico->value, Perfil::Dono->value, Perfil::Gerente->value, Perfil::Consultor->value])
            ->orderByRaw("case when perfil = 'mecanico' then 0 else 1 end")->orderBy('name')->get(['id', 'name', 'perfil']);
    }

    private function validar(Request $request, bool $nova): array
    {
        $request->merge([
            'km_entrada' => preg_replace('/\D/', '', (string) $request->input('km_entrada')) ?: null,
        ]);
        $regras = [
            'cliente_id' => ['nullable', Rule::exists('clientes', 'id')->where('empresa_id', Tenant::id())->whereNull('deleted_at')],
            'mecanico_id' => ['nullable', Rule::exists('users', 'id')->where('empresa_id', Tenant::id())],
            'km_entrada' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'combustivel' => ['nullable', 'integer', 'between:0,4'],
            'defeito_relatado' => ['nullable', 'string', 'max:5000'],
            'observacoes' => ['nullable', 'string', 'max:5000'],
            'previsao_entrega' => ['nullable', 'date'],
            'garantia_dias' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'checklist' => ['nullable', 'array'],
        ];
        if ($nova) {
            $regras['veiculo_id'] = ['required', Rule::exists('veiculos', 'id')->where('empresa_id', Tenant::id())->whereNull('deleted_at')];
        }
        $dados = $request->validate($regras, [], [
            'km_entrada' => 'km de entrada', 'defeito_relatado' => 'defeito relatado',
            'previsao_entrega' => 'previsão de entrega', 'garantia_dias' => 'garantia', 'mecanico_id' => 'mecânico',
        ]);
        // Em uma OS NOVA, o cliente precisa coincidir com o proprietário do veículo.
        // A abertura revalida isso sob bloqueio, caso ocorra transferência simultânea.
        // Em uma OS EXISTENTE, o cliente é um vínculo histórico e financeiro:
        // editar observações/garantia não pode transferir dívidas ou aceites para
        // o proprietário atual do veículo depois de uma venda.
        if ($nova) {
            $veiculo = Veiculo::findOrFail($dados['veiculo_id']);
            if (array_key_exists('cliente_id', $dados) && $dados['cliente_id'] !== null
                && (int) $dados['cliente_id'] !== (int) $veiculo->cliente_id) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'cliente_id' => 'O cliente selecionado não é o proprietário atual do veículo.',
                ]);
            }
            $dados['cliente_id'] = $veiculo->cliente_id;
        } else {
            $osOriginal = $request->route('os');
            if (array_key_exists('cliente_id', $dados)
                && (int) $dados['cliente_id'] !== (int) $osOriginal->cliente_id) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'cliente_id' => 'O cliente desta OS é histórico e não pode ser trocado nesta edição.',
                ]);
            }
            unset($dados['cliente_id']);
        }
        $dados['checklist'] = Checklist::limpar($request->input('checklist'));
        if ($nova) {
            // O padrão é somente para uma OS nova: em uma edição parcial,
            // ausência do campo não deve sobrescrever a garantia existente.
            $dados['garantia_dias'] ??= 90;
            $dados['consultor_id'] = auth()->id();
        }

        return $dados;
    }
}
