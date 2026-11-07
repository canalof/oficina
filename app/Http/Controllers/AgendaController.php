<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Lembrete;
use App\Models\Veiculo;
use App\Support\Placa;
use App\Support\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgendaController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('agenda.ver');
        $data = $request->query('semana') ? Carbon::parse($request->query('semana')) : today();
        $inicio = $data->copy()->startOfWeek(Carbon::MONDAY);
        $fim = $inicio->copy()->addDays(5)->endOfDay(); // segunda a sábado

        return view('agenda.index', [
            'inicio' => $inicio,
            'dias' => collect(range(0, 5))->map(fn ($i) => $inicio->copy()->addDays($i)),
            'porDia' => Agendamento::with(['veiculo', 'cliente'])->whereBetween('inicio', [$inicio, $fim])->orderBy('inicio')->get()
                ->groupBy(fn ($a) => $a->inicio->toDateString()),
            'novo' => $this->novo($request),
            'clientes' => Cliente::orderBy('nome')->get(['id', 'nome', 'whatsapp', 'telefone']),
            'editando' => $request->query('editar') ? Agendamento::find($request->query('editar')) : null,
        ]);
    }

    /** Pré-preenche a partir de um lembrete ou de um veículo. */
    private function novo(Request $request): Agendamento
    {
        $a = new Agendamento(['inicio' => today()->addDay()->setTime(8, 0), 'duracao_minutos' => 60]);
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
        $this->verificarLotacao($request, $dados);
        $a = Agendamento::create($dados + ['user_id' => auth()->id()]);
        if ($request->query('lembrete') || $request->input('lembrete_id')) {
            Lembrete::whereKey($request->input('lembrete_id'))->update(['status' => 'agendado']);
        }

        return redirect()->route('agenda.index', ['semana' => $a->inicio->toDateString()])->with('ok', 'Agendado para '.$a->inicio->translatedFormat('l, d/m \à\s H:i').'.');
    }

    public function update(Request $request, Agendamento $agendamento)
    {
        $this->authorize('agenda.editar');
        if ($request->has('status') && count($request->except(['_token', '_method', 'status'])) === 0) {
            $request->validate(['status' => ['required', Rule::in(array_keys(Agendamento::STATUS))]]);
            $agendamento->update(['status' => $request->input('status')]);

            return back()->with('ok', 'Marcado como "'.Agendamento::STATUS[$agendamento->status].'".');
        }
        $dados = $this->validar($request);
        $this->verificarLotacao($request, $dados, $agendamento->id);
        $agendamento->update($dados);

        return redirect()->route('agenda.index', ['semana' => $agendamento->inicio->toDateString()])->with('ok', 'Agendamento atualizado.');
    }

    public function destroy(Agendamento $agendamento)
    {
        $this->authorize('agenda.editar');
        $agendamento->delete();

        return back()->with('ok', 'Agendamento removido.');
    }

    /** Impede passar da capacidade (boxes/elevadores) no mesmo horário, a menos que marque "encaixar". */
    private function verificarLotacao(Request $request, array $dados, ?int $ignorar = null): void
    {
        if ($request->boolean('encaixar')) {
            return;
        }
        $ini = Carbon::parse($dados['inicio']);
        $fim = $ini->copy()->addMinutes((int) $dados['duracao_minutos']);
        $capacidade = max(1, (int) (\App\Models\Empresa::whereKey(Tenant::id())->value('agenda_capacidade') ?: 2));
        $mesmos = Agendamento::whereDate('inicio', $ini->toDateString())->whereNotIn('status', ['cancelado', 'faltou'])
            ->when($ignorar, fn ($q) => $q->where('id', '<>', $ignorar))->get()
            ->filter(fn ($a) => $a->inicio->lt($fim) && $a->inicio->copy()->addMinutes($a->duracao_minutos)->gt($ini));
        if ($mesmos->count() >= $capacidade) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'inicio' => 'Horário lotado: já há '.$mesmos->count().' carro(s) nesse horário (capacidade '.$capacidade.'). Escolha outro ou marque "Encaixar mesmo assim".',
            ]);
        }
    }

    private function validar(Request $request): array
    {
        $request->merge(['placa' => Placa::normalizar($request->input('placa')) ?: null]);
        $dados = $request->validate([
            'cliente_id' => ['nullable', Rule::exists('clientes', 'id')->where('empresa_id', Tenant::id())],
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
            $c = Cliente::find($dados['cliente_id']);
            $dados['nome'] = ($dados['nome'] ?? null) ?: $c->nome;
            $dados['telefone'] = ($dados['telefone'] ?? null) ?: ($c->whatsapp ?: $c->telefone);
        }
        $dados['veiculo_id'] = $dados['placa'] ? Veiculo::whereIn('placa', Placa::equivalentes($dados['placa']))->value('id') : null;

        return $dados;
    }
}
