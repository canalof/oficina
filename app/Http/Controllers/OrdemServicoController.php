<?php

namespace App\Http\Controllers;

use App\Models\OrdemServico;
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
use Illuminate\Validation\Rule;

class OrdemServicoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('os.ver');
        $visao = $request->query('visao') === 'todas' ? 'todas' : 'patio';

        if ($visao === 'patio') {
            $abertas = OrdemServico::with(['veiculo', 'cliente', 'mecanico'])
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
            'ordens' => OrdemServico::with(['veiculo', 'cliente'])
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
        $os = OrdemServico::abrir($dados);

        return redirect()->route('os.show', $os)->with('ok', 'OS '.$os->numeroFormatado().' aberta. Agora adicione os serviços e peças do orçamento.');
    }

    public function show(OrdemServico $os)
    {
        $this->authorize('os.ver');
        $os->load(['veiculo', 'cliente', 'consultor', 'mecanico', 'itens.mecanico', 'itens.produto', 'fotos', 'eventos.user', 'contasReceber', 'notas', 'aceites.user']);

        return view('os.show', [
            'os' => $os,
            'produtos' => \App\Models\Produto::where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'codigo', 'preco_centavos', 'estoque', 'unidade']),
            'caixaAberto' => \App\Models\Caixa::aberto() !== null,
            'servicos' => Servico::where('ativo', true)->orderBy('nome')->get(),
            'mecanicos' => $this->mecanicos(),
            'outras' => $os->veiculo->ordens()->where('id', '<>', $os->id)->with('itens')->limit(10)->get(),
        ]);
    }

    public function edit(OrdemServico $os)
    {
        $this->authorize('os.editar');
        abort_unless($os->status->noPatio(), 403, 'OS encerrada não pode ser alterada.');
        $os->load('veiculo.cliente');

        return view('os.form', ['os' => $os, 'veiculo' => $os->veiculo, 'aberta' => null, 'mecanicos' => $this->mecanicos(), 'clientes' => $this->clientes()]);
    }

    public function update(Request $request, OrdemServico $os)
    {
        $this->authorize('os.editar');
        abort_unless($os->status->noPatio(), 403, 'OS encerrada não pode ser alterada.');
        $os->update($this->validar($request, false));
        if ($os->km_entrada && $os->km_entrada > (int) $os->veiculo->km_atual) {
            $os->veiculo->update(['km_atual' => $os->km_entrada]);
        }
        $os->recalcular();

        return redirect()->route('os.show', $os)->with('ok', 'OS atualizada.');
    }

    /** Diagnóstico e mecânico responsável (o mecânico também pode preencher). */
    public function diagnostico(Request $request, OrdemServico $os)
    {
        $this->authorize('os.executar');
        abort_unless($os->status->noPatio(), 403);
        $dados = $request->validate([
            'diagnostico' => ['nullable', 'string', 'max:5000'],
            'mecanico_id' => ['nullable', Rule::exists('users', 'id')->where('empresa_id', Tenant::id())],
        ], [], ['mecanico_id' => 'mecânico']);
        $os->update($dados);

        return back()->with('ok', 'Diagnóstico salvo.');
    }

    public function desconto(Request $request, OrdemServico $os)
    {
        $this->authorize('os.editar');
        abort_unless($os->status->aceitaItens() || $os->status === StatusOs::Pronta, 403);
        $os->desconto_centavos = max(0, Dinheiro::paraCentavos($request->input('desconto')));
        $os->recalcular();
        $os->alterouOrcamento();
        $os->registrar('Desconto: '.Dinheiro::formatar($os->desconto_centavos));

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
        $dados['checklist'] = Checklist::limpar($request->input('checklist'));
        $dados['garantia_dias'] ??= 90;
        if ($nova) {
            $dados['consultor_id'] = auth()->id();
        }

        return $dados;
    }
}
