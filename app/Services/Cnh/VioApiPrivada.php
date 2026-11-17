<?php

namespace App\Services\Cnh;

use DomainException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * VIO API privada (vio.api.br, Gsutecnologia LTDA): lê o QR Code VIO a partir
 * de foto ou PDF da CNH e devolve os dados do documento.
 *
 * Leitura documental: comprova o documento apresentado, nunca a situação no
 * RENACH. Fluxo assíncrono: POST /api/qrcode/read devolve um id; o resultado
 * vem em GET /api/qrcode/result/{id}. Cobrança por documento VIO lido.
 * O fornecedor guarda o arquivo por 30 dias (política de privacidade dele).
 */
class VioApiPrivada implements ProvedorCnh
{
    /** Tipos aceitos e limite do arquivo enviado (bytes). */
    public const TIPOS = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    public const LIMITE_BYTES = 10 * 1024 * 1024;

    public function autenticidade(): string
    {
        return 'documental';
    }

    public function regras(): array
    {
        return ['api_key' => ['required', 'string', 'min:8', 'max:255']];
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('cnh.endpoints.vio_privado.url', 'https://vio.api.br'), '/');
    }

    /**
     * Confere a chave sem gastar crédito: pede o resultado de um id que não
     * existe. 404 = chave aceita; 401 = chave recusada.
     */
    public function testar(#[\SensitiveParameter] array $credenciais, #[\SensitiveParameter] array $entrada = []): void
    {
        $resposta = $this->enviar(fn () => $this->cliente($credenciais)->get('/api/qrcode/result/00000000-0000-4000-8000-000000000000'));
        if ($resposta->status() === 404 || $resposta->successful()) {
            return;
        }
        $this->falha($resposta);
    }

    public function consultar(#[\SensitiveParameter] array $credenciais, #[\SensitiveParameter] array $entrada): array
    {
        $conteudo = $entrada['arquivo'] ?? null;
        $tipo = (string) ($entrada['tipo'] ?? '');
        if (! is_string($conteudo) || $conteudo === '' || strlen($conteudo) > self::LIMITE_BYTES) {
            throw new DomainException('Envie uma foto ou PDF da CNH de até 10 MB.');
        }
        if (! in_array($tipo, self::TIPOS, true)) {
            throw new DomainException('Formato não aceito. Envie JPG, PNG, WEBP ou PDF.');
        }

        $envio = $this->enviar(fn () => $this->cliente($credenciais)
            ->post('/api/qrcode/read', ['image' => 'data:'.$tipo.';base64,'.base64_encode($conteudo)]));
        if (! $envio->successful()) {
            $this->falha($envio);
        }
        $id = $envio->json('id');
        if (! is_string($id) || ! preg_match('/^[A-Za-z0-9-]{8,64}$/', $id)) {
            throw new DomainException('A VIO API não confirmou o recebimento do documento.');
        }

        $resultado = $envio->json('status') === 'completed' ? $envio : null;
        $tentativas = max(1, (int) config('cnh.vio_privado.tentativas', 20));
        $espera = max(0, (int) config('cnh.vio_privado.espera_ms', 1000));
        for ($i = 0; ! $resultado && $i < $tentativas; $i++) {
            if ($espera > 0) {
                usleep($espera * 1000);
            }
            $resposta = $this->enviar(fn () => $this->cliente($credenciais)->get('/api/qrcode/result/'.$id));
            if (! $resposta->successful()) {
                $this->falha($resposta);
            }
            $status = $resposta->json('status');
            if ($status === 'completed') {
                $resultado = $resposta;
            } elseif (in_array($status, ['failed', 'error'], true)) {
                throw new DomainException('A VIO API não conseguiu ler o documento. Tente outra foto, mais nítida e sem reflexo, ou o PDF da CNH digital.');
            }
        }
        if (! $resultado) {
            throw new DomainException('A VIO API ainda está processando o documento. Tente novamente em instantes.');
        }

        $dados = $resultado->json('vio_result');
        if ($resultado->json('qr_type') !== 'vio' || ! is_array($dados) || $dados === []) {
            throw new DomainException('Não foi encontrado QR Code VIO legível no arquivo. Use o PDF da CNH digital ou fotografe o QR Code da CNH.');
        }

        return [
            'ambiente' => 'producao',
            'fonte' => 'vio_privado',
            'documento_teste' => 'CNH com QR Code VIO',
            'validade' => $this->campo($dados, ['Validade', 'Data de Validade']),
            'categoria' => $this->campo($dados, ['Cat. Hab.', 'Categoria', 'Cat Hab']),
            'nome' => $this->campo($dados, ['Nome']),
            'registro' => preg_replace('/\D/', '', (string) $this->campo($dados, ['Nº Registro', 'N° Registro', 'Registro'])) ?: null,
            'custo' => is_numeric($resultado->json('cost')) ? (float) $resultado->json('cost') : null,
        ];
    }

    private function cliente(#[\SensitiveParameter] array $credenciais): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withHeaders(['X-API-Key' => (string) ($credenciais['api_key'] ?? '')])
            ->acceptJson()
            ->connectTimeout((int) config('cnh.http.conectar_em', 5))
            ->timeout(max(20, (int) config('cnh.http.tempo_total', 12)));
    }

    /** @param  callable(): Response  $chamada */
    private function enviar(callable $chamada): Response
    {
        try {
            return $chamada();
        } catch (ConnectionException|GuzzleException) {
            throw new DomainException('A VIO API está indisponível. Tente novamente mais tarde.');
        }
    }

    private function falha(Response $resposta): never
    {
        throw new DomainException(match (true) {
            $resposta->status() === 401, $resposta->status() === 403 => 'A autenticação na VIO API não foi confirmada. Confira a API Key em Integrações de CNH.',
            $resposta->status() === 402 => 'Saldo insuficiente na VIO API. Recarregue os créditos no painel da VIO API.',
            $resposta->status() === 400 => 'A VIO API recusou o arquivo. Envie uma foto ou PDF da CNH com o QR Code visível.',
            $resposta->status() === 404 => 'A VIO API não encontrou o resultado desta leitura.',
            $resposta->status() === 429, $resposta->serverError() => 'A VIO API está indisponível (HTTP '.$resposta->status().'). Tente novamente mais tarde.',
            default => 'A VIO API não concluiu a leitura (HTTP '.$resposta->status().').',
        });
    }

    private function campo(array $dados, array $chaves): ?string
    {
        foreach ($chaves as $chave) {
            $valor = $dados[$chave] ?? null;
            if (is_string($valor) && trim($valor) !== '') {
                return mb_substr(trim($valor), 0, 120);
            }
        }

        return null;
    }
}
