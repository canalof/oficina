<?php

namespace App\Support;

use InvalidArgumentException;

/** Regras puras de ocupacao de boxes, sem acesso ao banco ou ao Laravel. */
final class AgendaCapacidade
{
    /**
     * @param list<array{inicio:int,fim:int}> $intervalos Reservas existentes.
     */
    public static function excedida(array $intervalos, int $inicio, int $fim, int $capacidade): bool
    {
        if ($fim <= $inicio || $capacidade < 1) {
            throw new InvalidArgumentException('Intervalo ou capacidade de agenda invalido.');
        }
        $eventos = [];
        foreach ($intervalos as $intervalo) {
            if (!isset($intervalo['inicio'], $intervalo['fim'])
                || !is_int($intervalo['inicio']) || !is_int($intervalo['fim'])
                || $intervalo['fim'] <= $intervalo['inicio']) {
                throw new InvalidArgumentException('Reserva de agenda invalida.');
            }
            $comeco = max($inicio, $intervalo['inicio']);
            $termino = min($fim, $intervalo['fim']);
            if ($comeco >= $termino) {
                continue; // intervalos adjacentes nao ocupam o mesmo box
            }
            $eventos[] = [$comeco, +1];
            $eventos[] = [$termino, -1];
        }
        // Em uma mesma marca de tempo, liberar o box antes de ocupa-lo.
        usort($eventos, static fn (array $a, array $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);
        $ocupacao = 0;
        foreach ($eventos as [, $variacao]) {
            $ocupacao += $variacao;
            if ($ocupacao >= $capacidade) {
                return true;
            }
        }
        return false;
    }
}
