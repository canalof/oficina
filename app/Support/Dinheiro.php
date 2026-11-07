<?php

namespace App\Support;

class Dinheiro
{
    /** "1.234,56" / "1234.56" / "R$ 80" → centavos (int). */
    public static function paraCentavos(mixed $valor): int
    {
        if (is_int($valor)) {
            return $valor * 100;
        }
        $v = preg_replace('/[^\d,.\-]/', '', (string) $valor);
        if ($v === '' || $v === '-') {
            return 0;
        }
        if (str_contains($v, ',')) {
            $v = str_replace(['.', ','], ['', '.'], $v);
        } elseif (substr_count($v, '.') > 1 || preg_match('/\.\d{3}$/', $v)) {
            $v = str_replace('.', '', $v); // "1.234" = mil duzentos e trinta e quatro
        }

        return (int) round(((float) $v) * 100);
    }

    public static function formatar(?int $centavos, bool $simbolo = true): string
    {
        return ($simbolo ? 'R$ ' : '').number_format(($centavos ?? 0) / 100, 2, ',', '.');
    }

    /** Valor para preencher um campo de formulário ("1.234,56"). */
    public static function campo(?int $centavos): string
    {
        return $centavos === null ? '' : number_format($centavos / 100, 2, ',', '.');
    }
}
