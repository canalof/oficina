<?php

namespace App\Services\Cobranca;

use App\Models\Empresa;
use App\Models\Plano;

class CobrancaDemonstracao implements Gateway
{
    public function nome(): string
    {
        return 'demonstracao';
    }

    public function assinar(Empresa $empresa, Plano $plano, string $email): array
    {
        return ['cliente_id' => 'demo_cli_'.$empresa->id, 'assinatura_id' => 'demo_sub_'.$empresa->id];
    }

    public function cancelar(Empresa $empresa): void {}
}
