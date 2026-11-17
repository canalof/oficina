<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\AgendaSolicitacao;
use App\Models\OrdemServico;
use App\Models\Empresa;
use App\Models\Cliente;
use App\Models\Lembrete;
use App\Models\Veiculo;
use App\Support\AgendaCapacidade;
use App\Support\Placa;
use App\Support\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AgendaController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('agenda.ver');
        // Evita erro 500 ao receber datas arbitrarias na navegacao semanal.
        $request->validate([
            'semana' => ['sometimes', 'date_format:Y-m-d'],
            'dia' => ['sometimes', 'date_format:Y-m-d'],
        ]);
        $data = $request->filled('semana') ? Carbon::parse($request->query('semana')) : today();
        $inicio = $data->copy()->startOfWeek(Carbon::MONDAY);
        $fim = $inicio->copy()->addDays(5)->endOfDay(); // segunda a sábado

        return view('agenda.index', [
            'inicio' => $inicio,
            'dias' => collect(range(0, 5))->map(fn ($i) => $inicio->copy()->addDays($i)),
            'porDia' => Agendamento::with(['veiculo', 'cliente'])->whereBetween('inicio', [$inicio, $fim])->orderBy('inicio')->get()
                ->groupBy(fn ($a) => $a->inicio->toDateString()),
            'novo' => $this->novo($request),
            'solicitacoes' => AgendaSolicitacao::with('veiculo')->where('status', 'pendente')->oldest('id')->paginate(15, ['*'], 'pedidos')->withQueryString(),
            'clientes' => Cliente::orderBy('nome')->get(['id', 'nome', 'whatsapp', 'telefone']),
            'editando' => $request->query('editar') ? Agendamento::find($request->query('editar')) : null,
        ]);
    }

    public function responder(Request $request, AgendaSolicitacao $solicitacao)
    {
        $this->authorize('agenda.editar');
        $dados = $request->validate([
            'decisao' => ['required', 'in:confirmar,recusar'],
            'inicio' => ['required_if:decisao,confirmar', 'nullable', 'date_format:Y-m-d\TH:i', 'after:now'],
            'duracao_minutos' => ['required_if:decisao,confirmar', 'nullable', 'integer', 'between:15,600'],
        ]);
        DB::transaction(function () use ($request, $solicitacao, $dados) {
            Empresa::whereKey(Tenant::id())->lockForUpdate()->firstOrFail();
            $atual = AgendaSolicitacao::whereKey($solicitacao->id)->lockForUpdate()->firstOrFail();
            abort_unless($atual->status === 'pendente', 409);
            if ($dados['decisao'] === 'recusar') {
                $atual->update(['status' => 'recusada']);

                return;
            }
            $cliente = Cliente::findOrFail($atual->cliente_id);
            $veiculo = $cliente->veiculos()->lockForUpdate()->findOrFail($atual->veiculo_id);
            $horario = ['inicio' => $dados['inicio'], 'duracao_minutos' => $dados['duracao_minutos']];
            $request->merge(['encaixar' => false]);
            $this->verificarLotacao($request, $horario);
            $proposta = $atual->preferencia->format('Y-m-d\TH:i') !== $dados['inicio'];
            $agendamento = Agendamento::create($horario + [
                'cliente_id' => $cliente->id, 'veiculo_id' => $veiculo->id, 'nome' => $cliente->nome,
                'placa' => $veiculo->placa, 'servico' => $atual->servico, 'status' => $proposta ? 'proposto' : 'confirmado', 'user_id' => auth()->id(),
            ]);
            $atual->update(['status' => $proposta ? 'proposta' : 'confirmada', 'agendamento_id' => $agendamento->id]);
        }, 3);

        return back()->with('ok', 'Solicitação respondida. O cliente pode consultar o resultado no portal.');
    }

    public function abrirOs(Agendamento $agendamento)
    {
        $this->authorize('agenda.editar');
        $this->authorize('os.editar');
        $os = DB::transaction(function () use ($agendamento) {
            Empresa::whereKey(Tenant::id())->lockForUpdate()->firstOrFail();
            $atual = Agendamento::whereKey($agendamento->id)->lockForUpdate()->firstOrFail();
            if ($atual->ordem_servico_id) {
                return OrdemServico::findOrFail($atual->ordem_servico_id);
            }
            abort_unless(in_array($atual->status, ['confirmado', 'chegou'], true) && $atual->veiculo_id && $atual->cliente_id, 409);
            $atual->veiculo()->whereNull('deleted_at')->where('cliente_id', $atual->cliente_id)->lockForUpdate()->firstOrFail();
            $os = OrdemServico::abrir([
                'veiculo_id' => $atual->veiculo_id, 'cliente_id' => $atual->cliente_id,
                'consultor_id' => auth()->id(), 'defeito_relatado' => $atual->servico,
            ]);
            $atual->update(['ordem_servico_id' => $os->id, 'status' => 'chegou']);

            return $os;
        }, 3);

        return redirect()->route('os.show', $os);
    }

    /** Pré-preenche a partir de um lembrete ou de um veículo. */
    private function novo(Request $request): Agendamento
    {
        $dia = $request->filled('dia') ? Carbon::parse($request->query('dia')) : today()->addDay();
        $a = new Agendamento(['inicio' => $dia->setTime(8, 0), 'duracao_minutos' => 60]);
        $v = null;
        if ($l = $request->query('lembrete') ? Lembrete::with('veiculo', 'cliente')->find($request->query('lembrete')) : null) {
            $v = $l->veiculo;
            $a->servico = $l->descricao;
        } elseif ($request->query('veiculo')) {
            $v = Veiculo::with('cliente')->find($request->query('veiculo'));
        }
        if ($v) {
            $a->fill(['veiculo_id' => $v->id, 'placa' => $v->placa, 'cliente_id' => $v->cliente_id, 'nome' => $v->cliente?->nome, 'telefone' => $v->cliente?->whatsapp ?: $v->cliente?->telefone]);
        }

        return $a;
    }

    public function store(Request $request)
    {
        $this->authorize('agenda.editar');
        $dados = $this->validar($request);
        // Serializar verificacao e inclusao por oficina para evitar que
        // dois atendentes reservem o ultimo box ao mesmo tempo no MariaDB.
        $a = DB::transaction(function () use ($request, $dados) {
            Empresa::whereKey(Tenant::id())->lockForUpdate()->firstOrFail();
            $this->verificarLotacao($request, $dados);
            $agendamento = Agendamento::create($dados + ['user_id' => auth()->id()]);

            // Vincular lembrete e agendamento na mesma transacao. O formulario
            // aceita lembrete_id e o atalho ?lembrete=ID; ambos sao restritos
            // a esta oficina e confrontados com cliente/veiculo agendados.
            $lembreteId = $request->input('lembrete_id') ?: $request->query('lembrete');
            if ($lembreteId && ctype_digit((string) $lembreteId)) {
                $lembrete = Lembrete::whereKey((int) $lembreteId)->lockForUpdate()->first();
                if ($lembrete && $lembrete->status === 'pendente'
                    && ($lembrete->cliente_id === null || (int) $lembrete->cliente_id === (int) ($agendamento->cliente_id ?? 0))
                    && ($lembrete->veiculo_id === null || (int) $lembrete->veiculo_id === (int) ($agendamento->veiculo_id ?? 0))
                    && ($lembrete->cliente_id !== null || $lembrete->veiculo_id !== null)) {
                    $lembrete->update(['status' => 'agendado']);
                }
            }

            return $agendamento;
        }, 3);
        return redirect()->route('agenda.index', ['semana' => $a->inicio->toDateString()])->with('ok', 'Agendado para '.$a->inicio->translatedFormat('l, d/m \à\s H:i').'.');
    }

    public function update(Request $request, Agendamento $agendamento)
    {
        $this->authorize('agenda.editar');
        if ($request->has('status') && count($request->except(['_token', '_method', 'status'])) === 0) {
            $request->validate(['status' => ['required', Rule::in(array_diff(array_keys(Agendamento::STATUS), ['proposto']))]]);
            $status = (string) $request->input('status');
            DB::transaction(function () use ($request, $agendamento, $status) {
                Empresa::whereKey(Tenant::id())->lockForUpdate()->firstOrFail();
                $atual = Agendamento::whereKey($agendamento->id)->lockForUpdate()->firstOrFail();
                abort_if($atual->status === 'proposto' && $status !== 'cancelado', 409);
                // Reativar um horario cancelado/faltoso consome capacidade.
                // Nao revalidar transicoes entre estados ativos para nao
                // bloquear um encaixe que ja tenha sido autorizado.
                if (in_array($atual->status, ['cancelado', 'faltou'], true)
                    && in_array($status, ['agendado', 'confirmado', 'chegou'], true)) {
                    $this->verificarLotacao($request, [
                        'inicio' => $atual->inicio,
                        'duracao_minutos' => $atual->duracao_minutos,
                    ], $atual->id);
                }
                $atual->update(['status' => $status]);
            }, 3);

            return back()->with('ok', 'Marcado como "'.Agendamento::STATUS[$status].'".');
        }
        $dados = $this->validar($request);
        DB::transaction(function () use ($request, $dados, $agendamento) {
            Empresa::whereKey(Tenant::id())->lockForUpdate()->firstOrFail();
            $agendamento = Agendamento::whereKey($agendamento->id)->lockForUpdate()->firstOrFail();
            abort_if($agendamento->status === 'proposto', 409);
            $this->verificarLotacao($request, $dados, $agendamento->id);
            $agendamento->update($dados);
        }, 3);

        return redirect()->route('agenda.index', ['semana' => $agendamento->inicio->toDateString()])->with('ok', 'Agendamento atualizado.');
    }

    public function destroy(Agendamento $agendamento)
    {
        $this->authorize('agenda.editar');
        $agendamento->delete();

        return back()->with('ok', 'Agendamento removido.');
    }

    /**
     * Testa a ocupacao simultanea em intervalos [inicio, fim), nao a mera
     * quantidade total de agendamentos que cruzam o horario solicitado.
     * Inclui agendamentos comecados no dia anterior (duracao maxima 600 min).
     * Chamar somente dentro da transacao com bloqueio da linha da empresa.
     */
    private function verificarLotacao(Request $request, array $dados, ?int $ignorar = null): void
    {
        if ($request->boolean('encaixar')) {
            return;
        }
        $ini = Carbon::parse($dados['inicio']);
        $fim = $ini->copy()->addMinutes((int) $dados['duracao_minutos']);
        $capacidade = max(1, (int) (Empresa::whereKey(Tenant::id())->value('agenda_capacidade') ?: 2));
        $candidatos = Agendamento::where('inicio', '>=', $ini->copy()->subMinutes(600))
            ->where('inicio', '<', $fim)
            ->whereNotIn('status', ['cancelado', 'faltou'])
            ->when($ignorar !== null, fn ($q) => $q->where('id', '<>', $ignorar))
            ->get(['inicio', 'duracao_minutos']);

        $intervalos = $candidatos->map(fn ($a) => [
            'inicio' => $a->inicio->getTimestamp(),
            'fim' => $a->inicio->copy()->addMinutes($a->duracao_minutos)->getTimestamp(),
        ])->all();
        if (AgendaCapacidade::excedida($intervalos, $ini->getTimestamp(), $fim->getTimestamp(), $capacidade)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'inicio' => 'Horario lotado para a capacidade de '.$capacidade.' box(es). Escolha outro horario ou marque "Encaixar mesmo assim".',
            ]);
        }
    }

    private function validar(Request $request): array
    {
        $request->merge(['placa' => Placa::normalizar($request->input('placa')) ?: null]);
        $dados = $request->validate([
            'cliente_id' => ['nullable', Rule::exists('clientes', 'id')->where('empresa_id', Tenant::id())->whereNull('deleted_at')],
            'nome' => ['required_without:cliente_id', 'nullable', 'string', 'max:120'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'placa' => ['nullable', function ($a, $v, $fail) {
                if ($v && ! Placa::valida($v)) {
                    $fail('Placa inválida.');
                }
            }],
            'inicio' => ['required', 'date'],
            'duracao_minutos' => ['required', 'integer', 'min:15', 'max:600'],
            'servico' => ['required', 'string', 'max:200'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ], ['nome.required_without' => 'Escolha o cliente ou escreva o nome.'], ['inicio' => 'data e hora', 'servico' => 'serviço', 'duracao_minutos' => 'duração']);

        if (! empty($dados['cliente_id'])) {
            $c = Cliente::findOrFail($dados['cliente_id']);
            $dados['nome'] = ($dados['nome'] ?? null) ?: $c->nome;
            $dados['telefone'] = ($dados['telefone'] ?? null) ?: ($c->whatsapp ?: $c->telefone);
        }
        $veiculo = $dados['placa']
            ? Veiculo::whereIn('placa', Placa::equivalentes($dados['placa']))->first()
            : null;
        if ($veiculo && ! empty($dados['cliente_id']) && (int) $veiculo->cliente_id !== (int) $dados['cliente_id']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'placa' => 'O veículo informado pertence a outro cliente desta oficina.',
            ]);
        }
        $dados['veiculo_id'] = $veiculo?->id;
        // Placa conhecida sem cliente selecionado deve manter o vinculo real
        // com o proprietario, em vez de criar agenda com cliente_id nulo.
        if ($veiculo && empty($dados['cliente_id']) && $veiculo->cliente_id) {
            $dono = Cliente::find($veiculo->cliente_id);
            if ($dono) {
                $dados['cliente_id'] = $dono->id;
                $dados['nome'] = $dono->nome;
                $dados['telefone'] = ($dados['telefone'] ?? null) ?: ($dono->whatsapp ?: $dono->telefone);
            }
        }

        return $dados;
    }
}
