<?php

namespace App\Support;

/**
 * Empresa (cliente do SaaS) em que a requisição ou o job está trabalhando.
 *
 * É definido pelo middleware DefinirEmpresa a partir do usuário logado.
 * Jobs e comandos que rodam fora de uma requisição chamam Tenant::usar($id).
 */
class Tenant
{
    private static ?int $empresaId = null;

    public static function usar(?int $empresaId): void
    {
        self::$empresaId = $empresaId;
    }

    public static function id(): ?int
    {
        return self::$empresaId;
    }

    public static function definido(): bool
    {
        return self::$empresaId !== null;
    }

    /** Executa um bloco com outra empresa e restaura a anterior. */
    public static function com(int $empresaId, callable $fn): mixed
    {
        $anterior = self::$empresaId;
        self::$empresaId = $empresaId;
        try {
            return $fn();
        } finally {
            self::$empresaId = $anterior;
        }
    }
}
