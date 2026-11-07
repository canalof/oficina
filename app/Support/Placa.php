<?php

namespace App\Support;

/**
 * Placas brasileiras: modelo antigo (ABC1234) e Mercosul (ABC1D23).
 *
 * Um carro que trocou a placa antiga pela Mercosul mantém as letras e números,
 * só o 5º caractere vira letra (0=A, 1=B ... 9=J). Por isso a busca procura as duas formas.
 */
class Placa
{
    public static function normalizar(?string $placa): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $placa));
    }

    public static function valida(?string $placa): bool
    {
        return (bool) preg_match('/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/', self::normalizar($placa));
    }

    public static function mercosul(?string $placa): bool
    {
        return (bool) preg_match('/^[A-Z]{3}[0-9][A-Z][0-9]{2}$/', self::normalizar($placa));
    }

    /** ABC1234 -> ABC-1234 ; ABC1D23 continua ABC1D23 */
    public static function formatar(?string $placa): string
    {
        $p = self::normalizar($placa);
        if (strlen($p) === 7 && ! self::mercosul($p)) {
            return substr($p, 0, 3).'-'.substr($p, 3);
        }

        return $p;
    }

    /** Formas equivalentes da mesma placa (antiga e Mercosul). */
    public static function equivalentes(?string $placa): array
    {
        $p = self::normalizar($placa);
        if (! self::valida($p)) {
            return [$p];
        }
        $quinto = $p[4];
        if (ctype_digit($quinto)) {
            $outra = substr($p, 0, 4).chr(ord('A') + (int) $quinto).substr($p, 5);
        } elseif ($quinto <= 'J') {
            $outra = substr($p, 0, 4).(ord($quinto) - ord('A')).substr($p, 5);
        } else {
            return [$p];
        }

        return [$p, $outra];
    }
}
