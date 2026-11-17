<?php

namespace App\Services\Cnh;

use App\Models\User;
use DomainException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class VioProvider
{
    public function decodificarEmHomologacao(User $usuario, string $conteudoQr): array
    {
        $this->validarConfiguracao($usuario, $conteudoQr);
        $configuracao = config('cnh.vio.'.$usuario->empresa_id);
        $cliente = Http::baseUrl('https://gateway.apiserpro.serpro.gov.br')
            ->acceptJson()->connectTimeout(5)->timeout(12)->withoutRedirecting();
        try {
            $token = $cliente->asForm()->withBasicAuth($configuracao['consumer_key'], $configuracao['consumer_secret'])
                ->post('/token', ['grant_type' => 'client_credentials'])->json('access_token');
            if (! is_string($token) || $token === '') {
                throw new DomainException('Não foi possível autenticar no Vio Decoder.');
            }
            $resposta = Http::baseUrl('https://gateway.apiserpro.serpro.gov.br')
                ->acceptJson()->connectTimeout(5)->timeout(12)->withoutRedirecting()
                ->withToken($token)->withBody($conteudoQr, 'application/octet-stream')->post('/viodec-trial/v1/decode');
        } catch (ConnectionException|GuzzleException) {
            throw new DomainException('O ambiente de teste do Vio está indisponível.');
        }
        $template = $resposta->json('template.name');
        if (! $resposta->successful() || ! is_string($template) || trim($template) === '') {
            throw new DomainException('O Vio não retornou uma decodificação conclusiva.');
        }

        return ['ambiente' => 'homologacao', 'template' => mb_substr($template, 0, 120)];
    }

    private function validarConfiguracao(User $usuario, string $conteudoQr): void
    {
        $configuracao = config('cnh.vio.'.$usuario->empresa_id);
        if (! $usuario->can('mobilidade.editar') || ($configuracao['autorizacao_validada'] ?? false) !== true
            || ! in_array($usuario->id, $configuracao['operadores'] ?? [], true)
            || empty($configuracao['consumer_key']) || empty($configuracao['consumer_secret'])) {
            throw new DomainException('Vio indisponível para esta oficina ou operador.');
        }
        if ($conteudoQr === '' || strlen($conteudoQr) > 16384) {
            throw new DomainException('Conteúdo binário do QR fora dos limites aceitos.');
        }
    }
}
