<?php

namespace App\Services\Placa;

interface ProvedorPlaca
{
    /** Nome curto gravado em placa_consultas.provedor */
    public function nome(): string;

    /**
     * Consulta a placa (já normalizada). Retorna null se o veículo não foi encontrado.
     *
     * @throws ConsultaPlacaIndisponivel quando o serviço está fora do ar ou recusou a chamada
     */
    public function consultar(string $placa): ?DadosVeiculo;

    /** Custo estimado desta consulta em centavos (para relatório de gastos). */
    public function custoCentavos(): int;
}
