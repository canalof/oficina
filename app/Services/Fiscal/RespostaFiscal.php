<?php

namespace App\Services\Fiscal;

final class RespostaFiscal
{
    public function __construct(
        public readonly string $status, // processando | autorizada | erro | cancelada
        public readonly ?string $numero = null,
        public readonly ?string $serie = null,
        public readonly ?string $chave = null,
        public readonly ?string $urlPdf = null,
        public readonly ?string $urlXml = null,
        public readonly ?string $mensagem = null,
        public readonly array $bruto = [],
    ) {}
}
