<?php

namespace App\Support;

/** RENAVAM: 11 dígitos, o último é dígito verificador. */
class Renavam
{
    public static function normalizar(?string $v): string
    {
        $d = preg_replace('/\D/', '', (string) $v);

        // Renavam antigo com 9 dígitos: completa com zeros à esquerda
        return $d !== '' && strlen($d) < 11 ? str_pad($d, 11, '0', STR_PAD_LEFT) : $d;
    }

    public static function digito(string $dezPrimeiros): int
    {
        $pesos = [3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $soma = 0;
        foreach (str_split($dezPrimeiros) as $i => $n) {
            $soma += (int) $n * $pesos[$i];
        }
        $dv = ($soma * 10) % 11;

        return $dv === 10 ? 0 : $dv;
    }

    public static function valido(?string $v): bool
    {
        $d = self::normalizar($v);
        if (strlen($d) !== 11 || preg_match('/^(\d)\1{10}$/', $d)) {
            return false;
        }

        return self::digito(substr($d, 0, 10)) === (int) $d[10];
    }
}
