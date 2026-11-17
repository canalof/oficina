<?php

namespace App\Services\Mobilidade;

use App\Models\ConferenciaCnh;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\MobilidadeCondutor;
use App\Models\MobilidadeFrota;
use App\Models\MobilidadeReserva;
use App\Models\OrdemServico;
use App\Services\Cnh\VerificationService;
use DomainException;
use Illuminate\Support\Facades\DB;

class Reservas
{
    public function reservar(array $dados): MobilidadeReserva
    {
        return DB::transaction(function () use ($dados) {
            OrdemServico::whereKey($dados['ordem_servico_id'])->lockForUpdate()->firstOrFail();
            MobilidadeCondutor::findOrFail($dados['condutor_id']);
            if ($dados['frota_id'] ?? null) {
                $frota = MobilidadeFrota::whereKey($dados['frota_id'])->lockForUpdate()->firstOrFail();
                $this->verificarDisponibilidade($frota, $dados['inicio'], $dados['fim']);
            }

            return MobilidadeReserva::create($dados + ['status' => 'reservada']);
        }, 3);
    }

    public function movimentar(MobilidadeReserva $reserva, string $acao, array $dados): MobilidadeReserva
    {
        return DB::transaction(function () use ($reserva, $acao, $dados) {
            $frota = $reserva->frota_id ? MobilidadeFrota::whereKey($reserva->frota_id)->lockForUpdate()->firstOrFail() : null;
            $reserva = MobilidadeReserva::whereKey($reserva->id)->lockForUpdate()->firstOrFail();
            match ($acao) {
                'entregar' => $this->entregar($reserva, $frota, $dados),
                'devolver' => $this->devolver($reserva, $frota, $dados),
                'prorrogar' => $this->prorrogar($reserva, $frota, $dados),
                'cancelar' => $this->cancelar($reserva),
            };

            return $reserva;
        }, 3);
    }

    private function verificarDisponibilidade(MobilidadeFrota $frota, string $inicio, string $fim, ?int $ignorar = null): void
    {
        if ($frota->situacao !== 'disponivel' || MobilidadeReserva::where('frota_id', $frota->id)
            ->when($ignorar, fn ($q) => $q->where('id', '<>', $ignorar))
            ->whereIn('status', ['reservada', 'entregue'])->where('inicio', '<', $fim)->where('fim', '>', $inicio)->exists()) {
            throw new DomainException('Veículo indisponível para o período informado.');
        }
    }

    private function prorrogar(MobilidadeReserva $r, ?MobilidadeFrota $frota, array $dados): void
    {
        if (! in_array($r->status, ['reservada', 'entregue'], true) || $dados['fim'] <= $r->fim->toDateTimeString()) {
            throw new DomainException('Informe uma data posterior ao fim atual de uma reserva em aberto.');
        }
        if ($frota) {
            $this->verificarDisponibilidade($frota, $r->inicio->toDateTimeString(), $dados['fim'], $r->id);
        }
        $r->update(['fim' => $dados['fim']]);
    }

    private function entregar(MobilidadeReserva $r, ?MobilidadeFrota $frota, array $dados): void
    {
        if ($r->status !== 'reservada' || now()->lt($r->inicio) || now()->gte($r->fim)
            || ($frota && ($frota->situacao !== 'disponivel' || $dados['km'] < $frota->km
            || MobilidadeReserva::where('frota_id', $frota->id)->where('status', 'entregue')->exists()))) {
            throw new DomainException('Não foi possível entregar: confira o estado da reserva, a disponibilidade e a quilometragem.');
        }
        $condutor = MobilidadeCondutor::findOrFail($r->condutor_id);
        $conferencia = ConferenciaCnh::where('condutor_id', $condutor->id)->where('finalidade', 'locacao')->latest('id')->first();
        app(VerificationService::class)->verificar(Empresa::findOrFail($r->empresa_id), $condutor, $conferencia);
        $r->update(['status' => 'entregue', 'entrega' => $dados + ['quando' => now()->toIso8601String(), 'user_id' => auth()->id()]]);
        $frota?->update(['km' => $dados['km']]);
    }

    private function devolver(MobilidadeReserva $r, ?MobilidadeFrota $frota, array $dados): void
    {
        if ($r->status !== 'entregue' || $dados['km'] < $r->entrega['km']) {
            throw new DomainException('A devolução exige entrega registrada e quilometragem final igual ou superior à inicial.');
        }
        $dias = max(1, (int) ceil($r->inicio->diffInSeconds($r->fim) / 86400));
        $valor = $dias * $r->diaria_centavos + $dados['taxas_centavos'];
        if ($valor > 999999999) {
            throw new DomainException('Valor da locação excede o limite aceito.');
        }
        $titulo = null;
        if ($valor > 0 && $r->tipo !== 'parceira') {
            $os = OrdemServico::findOrFail($r->ordem_servico_id);
            $titulo = ContaReceber::create([
                'cliente_id' => $os->cliente_id, 'ordem_servico_id' => $os->id,
                'descricao' => 'Mobilidade · reserva #'.$r->id, 'valor_centavos' => $valor,
                'vencimento' => today(), 'forma' => 'prazo',
            ]);
        }
        $r->update([
            'status' => 'devolvida', 'conta_receber_id' => $titulo?->id,
            'devolucao' => $dados + ['quando' => now()->toIso8601String(), 'user_id' => auth()->id()],
            'taxas_centavos' => $dados['taxas_centavos'], 'ocorrencias' => $dados['ocorrencias'] ?? null,
        ]);
        $frota?->update(['km' => $dados['km']]);
    }

    private function cancelar(MobilidadeReserva $r): void
    {
        if ($r->status !== 'reservada') {
            throw new DomainException('Só é possível cancelar uma reserva que ainda não foi entregue.');
        }
        $r->update(['status' => 'cancelada']);
    }
}
