<?php

namespace App\Support;

use DomainException;

/**
 * Traduz a operação escolhida na tela para o sentido físico do estoque.
 * O histórico guarda a origem, enquanto o tipo do movimento é entrada/saida.
 * Não acessa banco nem estado global: pode ser testado sem Laravel.
 */
final class MovimentacaoManual
{
    /** @return array{float, string, string, ?int} [quantidade com sinal, tipo, origem, custo] */
    public static function preparar(string $operacao, float $quantidade, ?int $custoInformado, ?int $custoAtual): array
    {
        if (! is_finite($quantidade) || $quantidade <= 0) {
            throw new DomainException('A quantidade da movimentação deve ser positiva.');
        }

        return match ($operacao) {
            'entrada' => [$quantidade, 'entrada', 'manual', $custoInformado ?? $custoAtual],
            'devolucao_cliente' => [$quantidade, 'entrada', 'devolucao_cliente', null],
            'devolucao_fornecedor' => [-$quantidade, 'saida', 'devolucao_fornecedor', null],
            'perda' => [-$quantidade, 'saida', 'perda', null],
            default => throw new DomainException('Tipo de movimentação não reconhecido.'),
        };
    }
}
