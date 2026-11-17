<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\MobilidadeCondutor;
use App\Models\MobilidadeFoto;
use App\Models\MobilidadeFrota;
use App\Models\MobilidadeReserva;
use App\Models\OrdemServico;
use App\Services\Mobilidade\Reservas;
use App\Support\Dinheiro;
use App\Support\Placa;
use App\Support\StatusOs;
use App\Support\Tenant;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Locação: módulo separado da oficina (ligado por empresa).
 * Duas modalidades: carro reserva (ligado a uma OS) e aluguel (só cliente).
 */
class LocacaoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('locacao.ver');
        $filtros = $request->validate([
            'modalidade' => ['sometimes', 'nullable', Rule::in(array_keys(MobilidadeReserva::MODALIDADES))],
            'situacao' => ['sometimes', 'nullable', Rule::in(['abertas', 'encerradas'])],
            'os' => ['sometimes', 'integer', 'min:1'],
            'cliente' => ['sometimes', 'integer', 'min:1'],
        ]);
        $os = isset($filtros['os']) ? OrdemServico::with('cliente')->findOrFail($filtros['os']) : null;
        $cliente = isset($filtros['cliente']) ? Cliente::findOrFail($filtros['cliente']) : null;
        $modalidade = $filtros['modalidade'] ?? null;
        $situacao = $filtros['situacao'] ?? 'abertas';

        $reservas = MobilidadeReserva::with(['cliente', 'ordemServico', 'frota', 'condutor'])
            ->when($modalidade, fn ($q) => $q->where('modalidade', $modalidade))
            ->when($os, fn ($q) => $q->where('ordem_servico_id', $os->id))
            ->when($cliente, fn ($q) => $q->where('cliente_id', $cliente->id))
            ->when($situacao === 'abertas', fn ($q) => $q->whereIn('status', ['reservada', 'entregue']))
            ->when($situacao === 'encerradas', fn ($q) => $q->whereIn('status', ['devolvida', 'cancelada']))
            ->orderByRaw("case status when 'entregue' then 0 when 'reservada' then 1 else 2 end")
            ->latest('inicio')->paginate(20)->withQueryString();

        $frota = MobilidadeFrota::orderBy('descricao')->get();
        $emUso = MobilidadeReserva::where('status', 'entregue')->whereNotNull('frota_id')->pluck('frota_id');

        return response()->view('locacao.index', [
            'os' => $os, 'cliente' => $cliente, 'modalidade' => $modalidade, 'situacao' => $situacao,
            'reservas' => $reservas,
            'frota' => $frota,
            'resumo' => [
                'disponiveis' => $frota->where('situacao', 'disponivel')->whereNotIn('id', $emUso)->count(),
                'emUso' => $emUso->count(),
                'saemHoje' => MobilidadeReserva::where('status', 'reservada')->whereDate('inicio', today())->count(),
                'voltamHoje' => MobilidadeReserva::where('status', 'entregue')->whereDate('fim', '<=', today())->count(),
            ],
            'ordens' => OrdemServico::with(['cliente', 'veiculo'])->whereIn('status', array_map(fn ($s) => $s->value, StatusOs::doPatio()))
                ->latest('id')->limit(100)->get(),
            'clientes' => Cliente::orderBy('nome')->limit(500)->get(['id', 'nome']),
            'condutores' => MobilidadeCondutor::orderBy('nome')->get(['id', 'nome', 'categoria', 'validade']),
            'fotosReserva' => MobilidadeFoto::whereIn('reserva_id', $reservas->getCollection()->modelKeys())->get()->groupBy('reserva_id'),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function frota(Request $request, ?MobilidadeFrota $frota = null)
    {
        $this->authorize('locacao.editar');
        $placa = Placa::normalizar($request->input('placa'));
        $request->merge(['placa' => $placa]);
        $dados = $request->validate([
            'placa' => ['required', fn ($campo, $valor, $falhar) => Placa::valida($valor) ?: $falhar('Placa inválida.'),
                Rule::unique('mobilidade_frota', 'placa')->where('empresa_id', Tenant::id())->ignore($frota?->id)],
            'descricao' => ['required', 'string', 'max:120'],
            'km' => ['required', 'integer', 'min:0', 'max:9999999'],
            'situacao' => ['required', Rule::in(['disponivel', 'manutencao', 'inativo'])],
            'manutencao' => ['nullable', 'string', 'max:4000'],
        ]);
        if ($frota?->exists) {
            $frota->update($dados);
        } else {
            MobilidadeFrota::create($dados);
        }

        return redirect()->to(route('locacao.index').'#frota')->with('ok', 'Carro da frota salvo.');
    }

    public function reservar(Request $request, Reservas $reservas)
    {
        $this->authorize('locacao.editar');
        $dados = $request->validate([
            'modalidade' => ['required', Rule::in(array_keys(MobilidadeReserva::MODALIDADES))],
            'ordem_servico_id' => ['required_if:modalidade,carro_reserva', 'prohibited_if:modalidade,aluguel', 'nullable', $this->existe('ordens_servico', true)],
            'cliente_id' => ['required_if:modalidade,aluguel', 'prohibited_if:modalidade,carro_reserva', 'nullable', $this->existe('clientes', true)],
            'condutor_id' => ['required', $this->existe('mobilidade_condutores', true)],
            'tipo' => ['required', Rule::in(array_keys(MobilidadeReserva::ORIGENS))],
            'frota_id' => ['required_unless:tipo,parceira', 'prohibited_if:tipo,parceira', 'nullable', $this->existe('mobilidade_frota')],
            'parceira' => ['required_if:tipo,parceira', 'nullable', 'string', 'max:120'],
            'contrato_externo' => ['nullable', 'string', 'max:120'],
            'inicio' => ['required', 'date_format:Y-m-d\TH:i'],
            'fim' => ['required', 'date_format:Y-m-d\TH:i', 'after:inicio'],
            'diaria' => ['nullable', 'string', 'max:20'],
            'caucao' => ['nullable', 'string', 'max:20'],
        ], [
            'ordem_servico_id.required_if' => 'Escolha a OS do cliente que vai usar o carro reserva.',
            'cliente_id.required_if' => 'Escolha o cliente do aluguel.',
        ]);
        $dados['inicio'] = Carbon::parse($dados['inicio'])->toDateTimeString();
        $dados['fim'] = Carbon::parse($dados['fim'])->toDateTimeString();
        $dados['diaria_centavos'] = $this->centavos($dados['diaria'] ?? null);
        $dados['caucao_centavos'] = $this->centavos($dados['caucao'] ?? null);
        unset($dados['diaria'], $dados['caucao']);
        try {
            $reserva = $reservas->reservar($dados);
        } catch (DomainException $e) {
            return back()->withInput()->with('erro', $e->getMessage());
        }

        return redirect()->to(route('locacao.index').'#reserva-'.$reserva->id)
            ->with('ok', $reserva->ehAluguel() ? 'Aluguel registrado.' : 'Carro reserva registrado.');
    }

    public function movimentar(Request $request, MobilidadeReserva $reserva, string $acao, Reservas $reservas)
    {
        $this->authorize('locacao.editar');
        $dados = match ($acao) {
            'prorrogar' => $request->validate(['fim' => ['required', 'date_format:Y-m-d\TH:i']]),
            'entregar', 'devolver' => $request->validate([
                'km' => ['required', 'integer', 'between:0,9999999'],
                'combustivel' => ['required', 'integer', 'between:0,4'],
                'vistoria' => ['required', 'string', 'max:4000'],
                'aceite_nome' => ['required', 'string', 'max:120'],
                'taxas' => ['nullable', 'string', 'max:20'],
                'ocorrencias' => ['nullable', 'string', 'max:4000'],
            ]),
            'cancelar' => [],
        };
        if ($acao === 'prorrogar') {
            $dados['fim'] = Carbon::parse($dados['fim'])->toDateTimeString();
        } elseif ($acao === 'devolver') {
            $dados['taxas_centavos'] = $this->centavos($dados['taxas'] ?? null);
        }
        unset($dados['taxas']);
        try {
            $reservas->movimentar($reserva, $acao, $dados);
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('ok', match ($acao) {
            'entregar' => 'Saída do carro registrada.',
            'devolver' => 'Devolução registrada.',
            'prorrogar' => 'Período prorrogado.',
            'cancelar' => 'Reserva cancelada.',
        });
    }

    /** Valor digitado ("1.234,56") em centavos; recusa negativo e acima de R$ 999.999,99. */
    private function centavos(?string $valor): int
    {
        $centavos = Dinheiro::paraCentavos($valor ?? '');
        abort_unless($centavos >= 0 && $centavos <= 99999999, 422, 'Valor inválido.');

        return $centavos;
    }

    private function existe(string $tabela, bool $softDelete = false)
    {
        $regra = Rule::exists($tabela, 'id')->where('empresa_id', Tenant::id());

        return $softDelete ? $regra->whereNull('deleted_at') : $regra;
    }
}
