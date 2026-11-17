<?php

namespace App\Services\Pix;

use App\Support\Documento;
use Illuminate\Support\Str;

class PixEstatico
{
    public static function chave(string $tipo, string $chave): ?string
    {
        $chave = trim($chave);
        $digitos = preg_replace('/\D/', '', $chave);

        return match ($tipo) {
            'cpf' => Documento::cpfValido($digitos) ? $digitos : null,
            'cnpj' => Documento::cnpjValido($digitos) ? $digitos : null,
            'telefone' => preg_match('/^\+[1-9]\d{1,14}$/D', $chave) ? $chave : null,
            'email' => filter_var($chave, FILTER_VALIDATE_EMAIL) ? strtolower($chave) : null,
            'aleatoria' => Str::isUuid($chave) ? strtolower($chave) : null,
            default => null,
        };
    }

    public static function texto(string $valor): string
    {
        return trim(preg_replace('/[^A-Z0-9 .&\/-]/', '', strtoupper(Str::ascii($valor))));
    }

    public static function gerar(string $chave, string $recebedor, string $cidade, int $centavos, string $referencia): string
    {
        $valor = sprintf('%d.%02d', intdiv($centavos, 100), $centavos % 100);
        $payload = self::campo('00', '01')
            .self::campo('26', self::campo('00', 'br.gov.bcb.pix').self::campo('01', $chave))
            .self::campo('52', '0000')
            .self::campo('53', '986')
            .self::campo('54', $valor)
            .self::campo('58', 'BR')
            .self::campo('59', $recebedor)
            .self::campo('60', $cidade)
            .self::campo('62', self::campo('05', $referencia))
            .'6304';

        $crc = 0xffff;
        foreach (unpack('C*', $payload) as $byte) {
            $crc ^= $byte << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = (($crc << 1) ^ (($crc & 0x8000) ? 0x1021 : 0)) & 0xffff;
            }
        }

        return $payload.sprintf('%04X', $crc);
    }

    private static function campo(string $id, string $valor): string
    {
        return $id.sprintf('%02d', strlen($valor)).$valor;
    }
}
