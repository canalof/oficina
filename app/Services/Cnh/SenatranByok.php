<?php

namespace App\Services\Cnh;

use App\Support\Documento;
use DomainException;

class SenatranByok implements ProvedorCnh
{
    public function __construct(private SenatranProvider $senatran) {}

    /**
     * A consulta SENATRAN é a única fonte declarada oficial nesta instalação.
     * A avaliação ainda converte para 'oficial' apenas em ambiente de produção.
     */
    public function autenticidade(): string
    {
        return 'oficial';
    }

    public function regras(): array
    {
        return [
            'certificado_pem' => ['required', 'string', 'max:10000'],
            'chave_pem' => ['required', 'string', 'max:8000'],
            'cpf_operador' => ['required', 'string', 'regex:/^\d{11}$/D', function ($campo, $cpf, $falhar) {
                if (! Documento::cpfValido($cpf)) {
                    $falhar('Informe um CPF de operador válido.');
                }
            }],
        ];
    }

    public function testar(#[\SensitiveParameter] array $credenciais, #[\SensitiveParameter] array $entrada = []): void
    {
        $this->consultar($credenciais, $entrada);
    }

    public function consultar(#[\SensitiveParameter] array $credenciais, #[\SensitiveParameter] array $entrada): array
    {
        $certificado = @openssl_x509_read($credenciais['certificado_pem']);
        $chave = @openssl_pkey_get_private($credenciais['chave_pem']);
        if (! $certificado || ! $chave || ! openssl_x509_check_private_key($certificado, $chave)) {
            throw new DomainException('O certificado e a chave PEM não correspondem.');
        }
        $cert = tmpfile();
        $key = tmpfile();
        try {
            if (! $cert || ! $key || fwrite($cert, $credenciais['certificado_pem']) !== strlen($credenciais['certificado_pem'])
                || fwrite($key, $credenciais['chave_pem']) !== strlen($credenciais['chave_pem'])) {
                throw new DomainException('Não foi possível preparar o certificado para a conexão.');
            }
            return $this->senatran->consultar([
                'certificado' => stream_get_meta_data($cert)['uri'],
                'chave' => stream_get_meta_data($key)['uri'],
                'cpf_operador' => $credenciais['cpf_operador'],
            ], $entrada['cpf'], $entrada['registro']);
        } finally {
            if ($cert) {
                fclose($cert);
            }
            if ($key) {
                fclose($key);
            }
        }
    }
}
