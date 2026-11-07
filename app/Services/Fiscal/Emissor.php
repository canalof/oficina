<?php

namespace App\Services\Fiscal;

interface Emissor
{
    public function nome(): string;

    /** Envia a nota. Devolve a resposta normalizada (ver RespostaFiscal). */
    public function enviar(string $tipo, string $referencia, array $dados): RespostaFiscal;

    public function consultar(string $tipo, string $referencia): RespostaFiscal;

    public function cancelar(string $tipo, string $referencia, string $justificativa): RespostaFiscal;
}
