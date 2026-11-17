<?php

namespace App\Support;

use InvalidArgumentException;

/** Valores informados pelo Excel: recusar dados inválidos sem arredondar silenciosamente. */
final class ImportacaoNumeros
{
    /** Quantidades de estoque usam exatamente até três casas decimais. */
    public static function quantidade(mixed $valor, string $campo): ?float
    {
        $texto = trim((string) ($valor ?? ''));
        if ($texto === '') {
            return null;
        }
        if (! preg_match('/^\d+(?:[,.]\d{1,3})?$/D', $texto)) {
            throw new InvalidArgumentException($campo.' inválido: use número não negativo com até três casas decimais.');
        }
        $quantidade = (float) str_replace(',', '.', $texto);
        if (! is_finite($quantidade) || $quantidade > 9999999) {
            throw new InvalidArgumentException($campo.' excede o máximo permitido (9999999).');
        }

        return $quantidade;
    }

    /** Aceita valores brasileiros e decimais simples; recusa letras, negativos e frações de centavo. */
    public static function centavos(mixed $valor, string $campo): ?int
    {
        $texto = trim((string) ($valor ?? ''));
        if ($texto === '') {
            return null;
        }
        $br = preg_match('/^(?:R\$\s*)?(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?$/uD', $texto);
        $decimal = preg_match('/^\d+\.\d{1,2}$/D', $texto);
        if (! $br && ! $decimal) {
            throw new InvalidArgumentException($campo.' inválido: use valor não negativo com até duas casas decimais.');
        }
        $inteiro = $decimal ? explode('.', $texto, 2)[0] : preg_replace('/[^0-9]/', '', explode(',', $texto, 2)[0]);
        if (strlen(ltrim($inteiro, '0')) > 12) {
            throw new InvalidArgumentException($campo.' excede o valor máximo permitido.');
        }

        return Dinheiro::paraCentavos($texto);
    }
}
