<?php

namespace App\Support;

/**
 * Código de 6 dígitos que muda a cada 30 segundos (RFC 6238),
 * compatível com Google Authenticator, Microsoft Authenticator, Authy etc.
 */
class Totp
{
    private const ALFABETO = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function novoSegredo(int $bytes = 20): string
    {
        return self::base32(random_bytes($bytes));
    }

    public static function codigo(string $segredo, ?int $momento = null): string
    {
        $contador = intdiv($momento ?? time(), 30);
        $hash = hash_hmac('sha1', pack('N*', 0).pack('N*', $contador), self::decodificar($segredo), true);
        $pos = ord($hash[19]) & 0xF;
        $num = ((ord($hash[$pos]) & 0x7F) << 24) | (ord($hash[$pos + 1]) << 16) | (ord($hash[$pos + 2]) << 8) | ord($hash[$pos + 3]);

        return str_pad((string) ($num % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** Aceita o código atual e o de 30 s antes/depois (relógio do celular adiantado/atrasado). */
    public static function valido(string $segredo, string $codigo, ?int $momento = null): bool
    {
        $codigo = preg_replace('/\D/', '', $codigo);
        if (strlen($codigo) !== 6) {
            return false;
        }
        $t = $momento ?? time();
        foreach ([-30, 0, 30] as $d) {
            if (hash_equals(self::codigo($segredo, $t + $d), $codigo)) {
                return true;
            }
        }

        return false;
    }

    public static function url(string $segredo, string $conta, string $emissor = 'Torque'): string
    {
        return 'otpauth://totp/'.rawurlencode($emissor.':'.$conta).'?secret='.$segredo.'&issuer='.rawurlencode($emissor).'&digits=6&period=30';
    }

    public static function base32(string $dados): string
    {
        $bits = '';
        foreach (str_split($dados) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $saida = '';
        foreach (str_split($bits, 5) as $bloco) {
            $saida .= self::ALFABETO[bindec(str_pad($bloco, 5, '0'))];
        }

        return $saida;
    }

    public static function decodificar(string $b32): string
    {
        $b32 = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32));
        $bits = '';
        foreach (str_split($b32) as $c) {
            $bits .= str_pad(decbin(strpos(self::ALFABETO, $c)), 5, '0', STR_PAD_LEFT);
        }
        $saida = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $saida .= chr(bindec($byte));
            }
        }

        return $saida;
    }
}
