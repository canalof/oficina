<?php

namespace App\Services\Fiscal;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Integração com a API v2 da Focus NFe.
 * Autenticação: token como usuário do HTTP Basic, senha vazia.
 */
class FocusNfe implements Emissor
{
    private string $base;

    public function __construct(private string $token, string $ambiente)
    {
        $this->base = rtrim(config('fiscal.focus.'.($ambiente === 'producao' ? 'producao' : 'homologacao')), '/');
    }

    public function nome(): string
    {
        return 'focus';
    }

    public function enviar(string $tipo, string $referencia, array $dados): RespostaFiscal
    {
        return $this->chamar(fn () => $this->http()->post("{$this->base}/v2/{$tipo}?ref=".rawurlencode($referencia), $dados));
    }

    public function consultar(string $tipo, string $referencia): RespostaFiscal
    {
        return $this->chamar(fn () => $this->http()->get("{$this->base}/v2/{$tipo}/".rawurlencode($referencia)));
    }

    public function cancelar(string $tipo, string $referencia, string $justificativa): RespostaFiscal
    {
        return $this->chamar(fn () => $this->http()->send('DELETE', "{$this->base}/v2/{$tipo}/".rawurlencode($referencia), ['json' => ['justificativa' => $justificativa]]));
    }

    private function http()
    {
        return Http::withBasicAuth($this->token, '')->acceptJson()->asJson()->timeout(40);
    }

    private function chamar(callable $fn): RespostaFiscal
    {
        try {
            /** @var Response $r */
            $r = $fn();
        } catch (ConnectionException $e) {
            return new RespostaFiscal('processando', mensagem: 'Sem resposta do emissor agora. Clique em "Atualizar situação" em alguns minutos.');
        }

        return $this->interpretar($r->json() ?? [], $r->status());
    }

    public function interpretar(array $j, int $http = 200): RespostaFiscal
    {
        $erros = collect($j['erros'] ?? [])->map(fn ($e) => $e['mensagem'] ?? json_encode($e, JSON_UNESCAPED_UNICODE))->join(' ');
        $mensagem = trim(($j['mensagem_sefaz'] ?? '').' '.($j['mensagem'] ?? '').' '.$erros) ?: null;

        if ($http >= 400 && ! isset($j['status'])) {
            return new RespostaFiscal('erro', mensagem: $mensagem ?: "Emissor recusou o envio (HTTP {$http}).", bruto: $j);
        }

        $status = match ($j['status'] ?? '') {
            'autorizado' => 'autorizada',
            'cancelado' => 'cancelada',
            'erro_autorizacao', 'denegado', 'erro_cancelamento' => ($j['status'] === 'erro_cancelamento' ? 'autorizada' : 'erro'),
            default => 'processando',
        };
        $url = fn (?string $c) => $c ? (str_starts_with($c, 'http') ? $c : $this->base.$c) : null;

        return new RespostaFiscal(
            status: $status,
            numero: isset($j['numero']) ? (string) $j['numero'] : null,
            serie: isset($j['serie']) ? (string) $j['serie'] : null,
            chave: $j['chave_nfe'] ?? $j['codigo_verificacao'] ?? null,
            urlPdf: $url($j['caminho_danfe'] ?? $j['url_danfse'] ?? $j['url'] ?? null),
            urlXml: $url($j['caminho_xml_nota_fiscal'] ?? null),
            mensagem: $mensagem,
            bruto: $j,
        );
    }
}
