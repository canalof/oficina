<?php

namespace App\Services\Fiscal;

/** Usado quando a oficina ainda não configurou o emissor. A nota não tem valor fiscal. */
class EmissorDemonstracao implements Emissor
{
    public function nome(): string
    {
        return 'demonstracao';
    }

    public function enviar(string $tipo, string $referencia, array $dados): RespostaFiscal
    {
        $numero = (string) (abs(crc32($referencia)) % 90000 + 10000);

        return new RespostaFiscal('autorizada', numero: $numero, serie: '1', mensagem: 'Modo demonstração: nota sem valor fiscal.', bruto: ['demonstracao' => true]);
    }

    public function consultar(string $tipo, string $referencia): RespostaFiscal
    {
        return $this->enviar($tipo, $referencia, []);
    }

    public function cancelar(string $tipo, string $referencia, string $justificativa): RespostaFiscal
    {
        return new RespostaFiscal('cancelada', mensagem: 'Cancelada (demonstração).');
    }
}
