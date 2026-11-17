<?php

namespace App\Services\Cnh;

use App\Models\CnhConsulta;
use App\Models\CnhIntegracao;
use App\Models\ConferenciaCnh;
use App\Models\MobilidadeCondutor;
use App\Models\User;
use DomainException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

/**
 * Ponto único de execução de consulta de CNH.
 *
 * Toda consulta que chega ao fornecedor — com ou sem condutor cadastrado, pela
 * tela do condutor, pela tela geral ou pelo modo legado — cria uma linha em
 * cnh_consultas antes da chamada e a atualiza com o resultado ou o motivo da
 * falha. O CPF nunca é persistido: apenas o HMAC-SHA256 (titular_hash).
 * Nunca grava CPF, QR ou credenciais nas evidências.
 */
class ConsultaCnh
{
    public function __construct(private CatalogoCnh $catalogo, private AvaliacaoCnh $avaliacao) {}

    public function executar(User $usuario, string $provedor, array $entrada, array $contexto = []): array
    {
        if (! $this->catalogo->existe($provedor)) {
            throw new DomainException('Fornecedor desconhecido. Nenhuma consulta foi executada.');
        }
        $adaptador = $this->catalogo->adaptador($provedor);
        if (! $adaptador) {
            throw new DomainException('O fornecedor selecionado não possui conector implementado nesta instalação. Nenhuma consulta foi executada.');
        }

        $integracao = CnhIntegracao::query()->where('provedor', $provedor)->where('ativa', true)->first();
        if (! $integracao) {
            throw new DomainException('A integração com este fornecedor não está ativa para a oficina. Configure, teste e ative em Integrações de CNH.');
        }

        $finalidade = $this->finalidade($contexto['finalidade'] ?? null);
        if (! in_array($finalidade, (array) $integracao->finalidades, true)) {
            throw new DomainException('A finalidade informada não está autorizada para esta integração.');
        }

        return $this->registrar($usuario, $provedor, $finalidade, $contexto, $adaptador->autenticidade(), function () use ($adaptador, $integracao, $entrada) {
            return $adaptador->consultar($integracao->credenciais, $entrada);
        });
    }

    /**
     * Caminho legado (configuração por oficina em config/cnh.php), mantido para
     * instalações antigas. Usa exatamente a mesma trilha de auditoria do BYOK.
     */
    public function executarLegado(User $usuario, string $provedor, string $finalidade, array $contexto, callable $executar): array
    {
        $finalidade = $this->finalidade($finalidade);
        $autenticidade = is_string($contexto['autenticidade'] ?? null) ? $contexto['autenticidade'] : 'nao_verificada';

        return $this->registrar($usuario, $provedor, $finalidade, $contexto, $autenticidade, $executar);
    }

    /**
     * @param  callable():array  $executar
     */
    private function registrar(User $usuario, string $provedor, string $finalidade, array $contexto, string $autenticidade, callable $executar): array
    {
        $condutor = ($contexto['condutor'] ?? null) instanceof MobilidadeCondutor ? $contexto['condutor'] : null;
        $os = $contexto['ordem_servico'] ?? null;
        $registro = CnhConsulta::create([
            'user_id' => $usuario->id,
            'condutor_id' => $condutor?->id,
            'titular_hash' => $this->titularHash($contexto['cpf'] ?? $condutor?->cpf),
            'ordem_servico_id' => $os?->id,
            'provedor' => $provedor,
            'finalidade' => $finalidade,
            'status' => 'em_execucao',
        ]);

        try {
            $bruto = $executar();
        } catch (\Throwable $erro) {
            $motivo = $this->motivo($erro);
            $registro->update([
                'status' => 'inconclusiva',
                'evidencias' => ['motivo' => $motivo, 'resultado' => 'erro', 'consultado_em' => now()->toIso8601String()],
            ]);
            // A mensagem original pode carregar URL com CPF; registra-se só o código e o hash.
            Log::warning('cnh.consulta.falha', [
                'provedor' => $provedor,
                'motivo' => $motivo,
                'user_id' => $usuario->id,
                'empresa_id' => $usuario->empresa_id,
                'titular_hash' => $registro->titular_hash,
                'consulta_id' => $registro->id,
            ]);

            throw $erro instanceof DomainException ? $erro : new DomainException('A consulta não foi concluída junto ao fornecedor.');
        }

        // A autenticidade é sempre a declarada pelo adaptador: o contexto não
        // pode promovê-la (defesa contra chamadas futuras que repassem entrada
        // do usuário no contexto).
        $avaliacao = $this->avaliacao->avaliar($provedor, $bruto, array_merge(
            ['ambiente' => $bruto['ambiente'] ?? config('cnh.ambiente', 'homologacao')],
            $contexto,
            ['autenticidade' => $autenticidade],
        ));
        $avaliacao['consulta_id'] = $registro->id;
        $avaliacao['titular_hash'] = $registro->titular_hash;

        $registro->update([
            'status' => match ($avaliacao['liberacao']) {
                'producao' => 'producao',
                default => $avaliacao['ambiente'] === 'homologacao' ? 'homologacao' : 'inconclusiva',
            },
            'evidencias' => $avaliacao,
        ]);

        // Só uma consulta oficial em produção gera conferência apta a liberar a operação.
        // Resultado de homologação fica apenas no histórico de consultas.
        if ($condutor && $avaliacao['liberacao'] === 'producao') {
            $conferencia = ConferenciaCnh::create([
                'ordem_servico_id' => $os?->id,
                'condutor_id' => $condutor->id,
                'condutor' => mb_substr((string) $condutor->nome, 0, 120),
                'user_id' => $usuario->id,
                'finalidade' => $finalidade,
                'metodo' => $provedor === 'senatran' ? 'situacao' : 'vio',
                'resultado' => $avaliacao['resultado'],
                'evidencias' => $avaliacao,
            ]);
            $avaliacao['conferencia_id'] = $conferencia->id;
        }

        return $avaliacao;
    }

    /** Situação da integração para a tela: ativa, testada e com conector. */
    public function situacao(string $provedor): array
    {
        $integracao = CnhIntegracao::query()->where('provedor', $provedor)->first();

        return [
            'implementado' => $this->catalogo->implementado($provedor),
            'configurado' => (bool) $integracao,
            'ativa' => (bool) $integracao?->ativa,
            'testada_ok' => (bool) $integracao?->teste_ok,
            'finalidades' => (array) ($integracao->finalidades ?? []),
            'ambiente' => $this->catalogo->ambiente($provedor),
        ];
    }

    private function finalidade(mixed $valor): string
    {
        $finalidade = is_string($valor) ? trim($valor) : '';
        if ($finalidade === '' || ! array_key_exists($finalidade, ConferenciaCnh::FINALIDADES)) {
            throw new DomainException('Informe uma finalidade legítima antes de consultar. Nenhuma consulta foi executada.');
        }

        return $finalidade;
    }

    /**
     * Identificador pseudônimo do titular: HMAC-SHA256 do CPF com a APP_KEY.
     * O CPF em claro não é persistido nem enviado a logs técnicos.
     */
    public function titularHash(?string $cpf): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $cpf);
        if (! is_string($digitos) || strlen($digitos) !== 11 || preg_match('/^0{11}$/', $digitos)) {
            return null;
        }

        return hash_hmac('sha256', $digitos, (string) config('app.key'));
    }

    private function motivo(\Throwable $erro): string
    {
        // Normaliza acentos: mensagens de fornecedores e do HTTP client variam.
        $mensagem = \Illuminate\Support\Str::ascii(mb_strtolower($erro->getMessage()));

        return match (true) {
            str_contains($mensagem, 'nao esta configurado') => 'endpoint_nao_configurado',
            str_contains($mensagem, 'indispon') => 'fornecedor_indisponivel',
            str_contains($mensagem, 'nenhum registro') => 'nao_encontrado',
            str_contains($mensagem, 'autentica') || str_contains($mensagem, 'credencia') => 'credenciais_recusadas',
            str_contains($mensagem, 'certificado') => 'certificado_invalido',
            default => 'falha_fornecedor',
        };
    }
}
