<?php

namespace App\Support;

/**
 * CPF e CNPJ, incluindo o CNPJ alfanumérico (letras nas 12 primeiras posições),
 * emitido pela Receita Federal a partir de julho de 2026.
 */
class Documento
{
    public static function normalizar(?string $doc): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $doc));
    }

    public static function tipo(?string $doc): ?string
    {
        $d = self::normalizar($doc);

        return match (strlen($d)) {
            11 => 'PF',
            14 => 'PJ',
            default => null,
        };
    }

    public static function valido(?string $doc): bool
    {
        $d = self::normalizar($doc);

        return match (strlen($d)) {
            11 => self::cpfValido($d),
            14 => self::cnpjValido($d),
            default => false,
        };
    }

    public static function cpfValido(string $cpf): bool
    {
        if (! preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $dv = ((10 * $soma) % 11) % 10;
            if ((int) $cpf[$t] !== $dv) {
                return false;
            }
        }

        return true;
    }

    public static function cnpjValido(string $cnpj): bool
    {
        if (! preg_match('/^[A-Z0-9]{12}\d{2}$/', $cnpj) || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }
        // Valor de cada caractere = código ASCII - 48 (dígitos ficam iguais; A=17, B=18...)
        $valores = array_map(fn ($c) => ord($c) - 48, str_split($cnpj));
        foreach ([12, 13] as $pos) {
            $pesos = $pos === 12 ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2] : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
            $soma = 0;
            foreach ($pesos as $i => $peso) {
                $soma += $valores[$i] * $peso;
            }
            $resto = $soma % 11;
            $dv = $resto < 2 ? 0 : 11 - $resto;
            if ($valores[$pos] !== $dv) {
                return false;
            }
        }

        return true;
    }

    public static function formatar(?string $doc): string
    {
        $d = self::normalizar($doc);
        if (strlen($d) === 11) {
            return substr($d, 0, 3).'.'.substr($d, 3, 3).'.'.substr($d, 6, 3).'-'.substr($d, 9);
        }
        if (strlen($d) === 14) {
            return substr($d, 0, 2).'.'.substr($d, 2, 3).'.'.substr($d, 5, 3).'/'.substr($d, 8, 4).'-'.substr($d, 12);
        }

        return (string) $doc;
    }
}
