<?php

namespace App\Services\Placa;

use App\Models\PlacaConsulta;
use App\Support\Placa;
use InvalidArgumentException;

/**
 * Consulta os dados de um veículo pela placa, reaproveitando consultas recentes.
 *
 * Se a mesma placa foi consultada com sucesso nos últimos N dias (config placa.reuso_dias),
 * devolve o resultado guardado sem custo. Toda chamada ao provedor fica registrada em
 * placa_consultas, com o custo, para o relatório de gastos.
 */
class ConsultaPlaca
{
    public function __construct(private readonly ProvedorPlaca $provedor) {}

    /** @return array{dados: ?DadosVeiculo, origem: string} origem = cache|provedor */
    public function buscar(string $placa): array
    {
        $placa = Placa::normalizar($placa);
        if (! Placa::valida($placa)) {
            throw new InvalidArgumentException('Placa inválida.');
        }

        $recente = PlacaConsulta::query()
            ->whereIn('placa', Placa::equivalentes($placa))
            ->where('status', 'encontrado')
            ->where('created_at', '>=', now()->subDays((int) config('placa.reuso_dias', 30)))
            ->latest('id')
            ->first();

        if ($recente) {
            return ['dados' => DadosVeiculo::fromArray($recente->resposta), 'origem' => 'cache'];
        }

        // Limite mensal do plano (só conta consulta paga)
        $limite = \App\Models\Empresa::whereKey(\App\Support\Tenant::id())->first()?->plano?->limite_consultas_placa;
        if ($limite !== null && $this->provedor->nome() !== 'demonstracao'
            && PlacaConsulta::where('created_at', '>=', now()->startOfMonth())->where('custo_centavos', '>', 0)->count() >= $limite) {
            throw new ConsultaPlacaIndisponivel("O plano chegou ao limite de {$limite} consultas de placa neste mês. Preencha os dados à mão ou troque de plano.");
        }

        try {
            $dados = $this->provedor->consultar($placa);
        } catch (ConsultaPlacaIndisponivel $e) {
            PlacaConsulta::create([
                'user_id' => auth()->id(), 'placa' => $placa, 'provedor' => $this->provedor->nome(),
                'status' => 'erro', 'resposta' => ['erro' => $e->getMessage()], 'custo_centavos' => 0,
            ]);
            throw $e;
        }

        PlacaConsulta::create([
            'user_id' => auth()->id(),
            'placa' => $placa,
            'provedor' => $this->provedor->nome(),
            'status' => $dados ? 'encontrado' : 'nao_encontrado',
            'resposta' => $dados?->toArray(),
            'custo_centavos' => $this->provedor->custoCentavos(),
        ]);

        return ['dados' => $dados, 'origem' => 'provedor'];
    }
}
