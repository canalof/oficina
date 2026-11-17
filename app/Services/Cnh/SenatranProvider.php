<?php

namespace App\Services\Cnh;

use App\Models\ConferenciaCnh;
use App\Models\User;
use App\Support\Documento;
use DomainException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SenatranProvider
{
    public const AMBIENTES = ['homologacao' => 'Homologação', 'producao' => 'Produção'];

    /** O cliente legado também consulta a base oficial; a avaliação converte para 'oficial' só em produção. */
    public function autenticidade(): string
    {
        return 'oficial';
    }

    public function ambiente(): string
    {
        return (string) config('cnh.ambiente', 'homologacao') === 'producao' ? 'producao' : 'homologacao';
    }

    /**
     * Resolve o endereço do ambiente pedido. Nenhuma URL de produção é presumida:
     * sem CNH_SENATRAN_URL_PRODUCAO a consulta é recusada antes de qualquer envio.
     */
    public function endpoint(?string $ambiente = null): string
    {
        $ambiente = $this->normalizarAmbiente($ambiente);
        $url = trim((string) config('cnh.endpoints.senatran.'.$ambiente, ''));
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new DomainException('O endereço da SENATRAN para '.self::AMBIENTES[$ambiente].' não está configurado nesta instalação. Nenhuma consulta foi enviada; defina CNH_SENATRAN_URL_'.strtoupper($ambiente).' depois do credenciamento.');
        }

        return rtrim($url, '/');
    }

    public function configuracao(User $usuario): ?array
    {
        $oficina = config('cnh.oficinas.'.$usuario->empresa_id);
        $cpfOperador = $oficina['operadores'][$usuario->id] ?? null;
        $finalidades = array_values(array_intersect(array_keys(ConferenciaCnh::FINALIDADES), (array) ($oficina['finalidades'] ?? [])));
        if (! is_string($cpfOperador) || ! Documento::cpfValido($cpfOperador)
            || ! is_string($oficina['certificado'] ?? null) || ! is_readable($oficina['certificado'])
            || ! is_string($oficina['chave'] ?? null) || ! is_readable($oficina['chave'])
            || ($oficina['autorizacao_validada'] ?? false) !== true
            || ! $finalidades) {
            return null;
        }

        return ['cpf_operador' => $cpfOperador, 'finalidades' => $finalidades] + $oficina;
    }

    public function consultar(array $configuracao, string $cpf, string $registro): array
    {
        $ambiente = $this->normalizarAmbiente($configuracao['ambiente'] ?? null);
        if (! is_string($cpf) || preg_match('/^\d{11}$/D', $cpf) !== 1
            || ! is_string($registro) || preg_match('/^\d{11}$/D', $registro) !== 1) {
            throw new DomainException('Informe CPF e registro da CNH com 11 dígitos.');
        }
        $url = $this->endpoint($ambiente).sprintf((string) config('cnh.senatran.recurso', '/v1/condutores/cpf/%s/registroCnh/%s'), $cpf, $registro);

        try {
            $resposta = Http::withOptions([
                'cert' => $configuracao['certificado'],
                'ssl_key' => $configuracao['chave'],
                'allow_redirects' => false,
            ])->withHeader('x-cpf-usuario', (string) $configuracao['cpf_operador'])
                ->acceptJson()
                ->connectTimeout((int) config('cnh.http.conectar_em', 5))
                ->timeout((int) config('cnh.http.tempo_total', 12))
                ->get($url);
        } catch (ConnectionException|GuzzleException) {
            throw new DomainException('O serviço oficial da SENATRAN ('.self::AMBIENTES[$ambiente].') está indisponível. Tente novamente mais tarde.');
        }

        if ($resposta->status() === 404) {
            throw new DomainException('Nenhum registro encontrado para os dados informados.');
        }
        if (in_array($resposta->status(), [401, 403], true)) {
            throw new DomainException('A autenticação da oficina foi recusada pela SENATRAN ('.self::AMBIENTES[$ambiente].'). Confira o certificado e o credenciamento do operador.');
        }
        if ($resposta->status() === 429 || $resposta->serverError()) {
            // P-249-07: erro interno ou limite do fornecedor não é credencial recusada.
            throw new DomainException('O serviço oficial da SENATRAN ('.self::AMBIENTES[$ambiente].') está indisponível (HTTP '.$resposta->status().'). Tente novamente mais tarde.');
        }
        if (! $resposta->successful()) {
            throw new DomainException('A consulta não foi concluída pela SENATRAN ('.self::AMBIENTES[$ambiente].') com HTTP '.$resposta->status().'.');
        }

        $registros = $resposta->json('condutores');
        if (! is_array($registros) || count($registros) !== 1 || ! is_array($registros[0] ?? null)) {
            throw new DomainException('A resposta oficial não trouxe um registro único para conferência.');
        }
        $condutor = $registros[0];
        $impedimentos = $condutor['quantidadeOcorrenciasImpedimentos'] ?? null;

        return [
            'ambiente' => $ambiente,
            'fonte' => 'senatran',
            'categoria' => $this->campo($condutor, 'categoriaAtual'),
            'validade' => $this->campo($condutor, 'dataValidadeCnh'),
            'situacao' => $this->campo($condutor, 'descricaoSituacaoCnh') ?? $this->campo($condutor, 'situacaoCnh'),
            'impedimentos' => is_int($impedimentos) && $impedimentos >= 0 ? $impedimentos : null,
        ];
    }

    public function normalizarAmbiente(?string $ambiente): string
    {
        return $ambiente === 'producao' || $ambiente === 'homologacao' ? $ambiente : $this->ambiente();
    }

    private function campo(array $dados, string $campo): ?string
    {
        $valor = $dados[$campo] ?? null;

        return is_string($valor) && trim($valor) !== '' ? mb_substr(trim($valor), 0, 100) : null;
    }
}
