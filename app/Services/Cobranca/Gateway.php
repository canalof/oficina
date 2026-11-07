<?php

namespace App\Services\Cobranca;

use App\Models\Empresa;
use App\Models\Plano;

interface Gateway
{
    public function nome(): string;

    /** Cria (ou troca) a assinatura mensal. Devolve ['cliente_id' => ..., 'assinatura_id' => ...]. */
    public function assinar(Empresa $empresa, Plano $plano, string $email): array;

    public function cancelar(Empresa $empresa): void;
}
