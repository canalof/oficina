<?php

namespace App\Services\Cnh;

use DomainException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class VioByok implements ProvedorCnh
{
    public const AMBIENTES = ['homologacao' => 'Homologação', 'producao' => 'Produção'];

    /** Leitura documental do QR/PDF; nunca comprova a situação no RENACH. */
    public function autenticidade(): string
    {
        return 'documental';
    }

    public function regras(): array
    {
        return ['consumer_key' => ['required', 'string', 'max:255'], 'consumer_secret' => ['required', 'string', 'max:255']];
    }

    public function ambiente(): string
    {
        return (string) config('cnh.ambiente', 'homologacao') === 'producao' ? 'producao' : 'homologacao';
    }

    public function baseUrl(?string $ambiente = null): string
    {
        $ambiente = $ambiente === 'producao' || $ambiente === 'homologacao' ? $ambiente : $this->ambiente();
        $url = trim((string) config('cnh.endpoints.vio.'.$ambiente, ''));
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new DomainException('O endereço do SERPRO/Vio para '.self::AMBIENTES[$ambiente].' não está configurado nesta instalação. Nenhuma chamada foi enviada; defina CNH_VIO_URL_'.strtoupper($ambiente).' depois do credenciamento.');
        }

        return rtrim($url, '/');
    }

    public function testar(#[\SensitiveParameter] array $credenciais, #[\SensitiveParameter] array $entrada = []): void
    {
        $this->token($credenciais);
    }

    public function consultar(#[\SensitiveParameter] array $credenciais, #[\SensitiveParameter] array $entrada): array
    {
        $token = $this->token($credenciais);
        $corpo = $entrada['qr'] ?? '';
        if (! is_string($corpo) || $corpo === '' || strlen($corpo) > 16384) {
            throw new DomainException('Conteúdo binário do QR ausente ou fora dos limites aceitos (até 16 KB).');
        }
        try {
            $resposta = $this->cliente()->withToken($token)
                ->withBody($corpo, 'application/octet-stream')
                ->post((string) config('cnh.endpoints.vio.decode', '/viodec-trial/v1/decode'));
        } catch (ConnectionException|GuzzleException) {
            throw new DomainException('O ambiente do Vio está indisponível. Tente novamente mais tarde.');
        }
        if (in_array($resposta->status(), [401, 403], true)) {
            throw new DomainException('A autenticação no Serpro não foi confirmada.');
        }
        if ($resposta->status() === 429 || $resposta->serverError()) {
            throw new DomainException('O ambiente do Vio está indisponível (HTTP '.$resposta->status().'). Tente novamente mais tarde.');
        }
        $template = $resposta->json('template.name');
        if (! $resposta->successful() || ! is_string($template) || trim($template) === '') {
            throw new DomainException('O Vio não retornou uma decodificação conclusiva.');
        }

        return [
            'ambiente' => $this->ambiente(),
            'fonte' => 'vio',
            'documento_teste' => mb_substr(trim($template), 0, 80),
        ];
    }

    private function token(#[\SensitiveParameter] array $credenciais): string
    {
        try {
            $resposta = $this->cliente()->asForm()
                ->withBasicAuth((string) ($credenciais['consumer_key'] ?? ''), (string) ($credenciais['consumer_secret'] ?? ''))
                ->post((string) config('cnh.endpoints.vio.token', '/token'), ['grant_type' => 'client_credentials']);
        } catch (ConnectionException|GuzzleException) {
            throw new DomainException('O ambiente do SERPRO está indisponível. Tente novamente mais tarde.');
        }
        if (in_array($resposta->status(), [401, 403], true)) {
            throw new DomainException('A autenticação no Serpro não foi confirmada.');
        }
        if ($resposta->status() === 429 || $resposta->serverError()) {
            throw new DomainException('O ambiente do SERPRO está indisponível (HTTP '.$resposta->status().'). Tente novamente mais tarde.');
        }
        $token = $resposta->json('access_token');
        if (! $resposta->successful() || ! is_string($token) || $token === '') {
            throw new DomainException('A autenticação no Serpro não foi confirmada.');
        }

        return $token;
    }

    private function cliente()
    {
        return Http::baseUrl($this->baseUrl())->acceptJson()
            ->connectTimeout((int) config('cnh.http.conectar_em', 5))
            ->timeout((int) config('cnh.http.tempo_total', 12))
            ->withoutRedirecting();
    }
}
