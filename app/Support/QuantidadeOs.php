<?php

namespace App\Support;

/** Precisão decimal compatível com ordem_servico_itens.quantidade (DECIMAL(10,3)). */
final class QuantidadeOs
{
    public static function normalizar(mixed $valor, bool $padraoUm = false): string
    {
        $texto = trim((string) $valor);
        if ($padraoUm && $texto === '') {
            return '1';
        }

        return str_replace(',', '.', $texto);
    }

    public static function precisaoValida(mixed $valor): bool
    {
        return (is_string($valor) || is_int($valor) || is_float($valor))
            && preg_match('/\A\d+(?:\.\d{1,3})?\z/D', (string) $valor) === 1;
    }
}
