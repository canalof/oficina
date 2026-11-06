<?php

namespace App\Services\Placa;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Provedor consultadeplaca.net (https://consultadeplaca.net/desenvolvedores).
 *
 * POST /api/v1/lookups com {"plate": "ABC1D23"}, chave em "Authorization: Bearer" e
 * cabeçalho Idempotency-Key. Resposta 201 com result = found | not_found e o objeto vehicle.
 * Cobra por consulta concluída (found ou not_found); 503 não cobra.
 * Não traz dados do proprietário. Use chave cdp_test_ em desenvolvimento
 * (placas TST0001 = encontrada, TST0002 = não encontrada, TST0003 = 503).
 */
class ConsultaDePlacaNet implements ProvedorPlaca
{
    public function __construct(
        private readonly string $chave,
        private readonly string $url = 'https://consultadeplaca.net/api/v1',
        private readonly int $custo = 7,
    ) {}

    public function nome(): string
    {
        return 'consultadeplaca';
    }

    public function custoCentavos(): int
    {
        return $this->custo;
    }

    public function consultar(string $placa): ?DadosVeiculo
    {
        $idempotencia = 'oficina-'.$placa.'-'.Str::uuid();

        try {
            $resp = Http::withToken($this->chave)
                ->acceptJson()
                ->asJson()
                ->withHeaders(['Idempotency-Key' => $idempotencia])
                ->timeout(20)
                // repete com a MESMA chave só em erro interno/limite, como pede a documentação
                ->retry(2, 2000, fn ($e, $req) => in_array(optional($e->response ?? null)->status(), [409, 429, 500], true), throw: false)
                ->post(rtrim($this->url, '/').'/lookups', ['plate' => $placa]);
        } catch (ConnectionException $e) {
            throw new ConsultaPlacaIndisponivel('Sem conexão com o serviço de placas.', 0, $e);
        }

        if (! $resp->successful()) {
            $codigo = $resp->json('code') ?? 'http_'.$resp->status();
            $msg = match ($codigo) {
                'insufficient_balance' => 'Saldo da consulta de placa acabou. Recarregue a conta do provedor.',
                'daily_limit_exceeded' => 'Limite diário de consultas de placa atingido.',
                'invalid_api_key', 'api_key_expired', 'api_key_revoked', 'missing_api_key' => 'Chave da API de placa inválida. Confira a configuração.',
                'rate_limited' => 'Muitas consultas seguidas. Tente de novo em alguns segundos.',
                default => 'Serviço de placas indisponível ('.$codigo.').',
            };
            throw new ConsultaPlacaIndisponivel($msg);
        }

        $j = $resp->json();
        if (($j['result'] ?? null) !== 'found' || empty($j['vehicle'])) {
            return null;
        }

        $v = $j['vehicle'];
        $fipe = $v['fipe'] ?? [];

        return new DadosVeiculo(
            placa: $placa,
            marca: $this->texto($v['brand'] ?? null),
            modelo: $this->texto($v['model'] ?? null),
            versao: $this->texto($v['version'] ?? null, false),
            anoFabricacao: $this->int($v['manufacture_year'] ?? null),
            anoModelo: $this->int($v['model_year'] ?? null),
            cor: $this->texto($v['color'] ?? null),
            combustivel: $this->texto($v['fuel'] ?? null),
            fipeCodigo: $fipe['code'] ?? null,
            fipeValorCentavos: isset($fipe['amount']) && is_numeric($fipe['amount']) ? (int) round(((float) $fipe['amount']) * 100) : null,
            municipio: $this->texto($v['city'] ?? null),
            uf: isset($v['state']) ? strtoupper((string) $v['state']) : null,
            rouboFurto: (bool) ($v['theft'] ?? false),
        );
    }

    private function texto($v, bool $titulo = true): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }

        return $titulo ? mb_convert_case(mb_strtolower($v), MB_CASE_TITLE) : $v;
    }

    private function int($v): ?int
    {
        return is_numeric($v) ? (int) $v : null;
    }
}
