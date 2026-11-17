<?php

namespace App\Support;

/** Neutraliza fórmulas de planilhas mesmo quando precedidas por espaços, tabs ou quebras de linha. */
final class CsvSeguro
{
    public static function celula(mixed $valor): mixed
    {
        return is_string($valor) && preg_match('/\A[\p{Z}\p{C}]*[=+\-@]/u', $valor)
            ? "'".$valor
            : $valor;
    }
}
