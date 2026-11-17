<?php

namespace App\Services\Cnh;

use Illuminate\Support\Carbon;

/**
 * Normaliza a resposta de qualquer fornecedor em cinco dimensões explícitas:
 * autenticidade, validade, categoria, situação atual e restrições.
 *
 * Regra central: só uma consulta oficial (RENACH/SENATRAN) feita em ambiente de
 * produção libera retirada ou locação. Homologação e leitura documental provam
 * o fluxo, nunca a situação real do condutor. A autenticidade é declarada pelo
 * adaptador (ProvedorCnh::autenticidade) e só é aceita como oficial quando o
 * provedor também está na lista fechada FONTES_OFICIAIS.
 */
class AvaliacaoCnh
{
    public const DIMENSOES = [
        'autenticidade' => 'Autenticidade',
        'validade' => 'Validade',
        'categoria' => 'Categoria',
        'situacao' => 'Situação atual',
        'restricoes' => 'Restrições',
    ];

    public const GARANTIAS = [
        'oficial' => 'Consulta oficial ao RENACH/SENATRAN em produção.',
        'oficial_homologacao' => 'Consulta oficial em homologação: comprova o fluxo, não a situação real do condutor.',
        'documental' => 'Leitura documental: comprova o documento apresentado, não a situação no RENACH.',
        'nao_verificada' => 'Nenhuma fonte externa comprovou a autenticidade.',
    ];

    public const LIBERACOES = [
        'producao' => 'Libera retirada e locação',
        'homologacao' => 'Não libera: resultado de homologação',
        'nao_liberado' => 'Não libera: conferência incompleta ou divergente',
    ];

    /**
     * Lista fechada de fontes autorizadas a declarar autenticidade oficial.
     * Minha CNH e VIO privado são diagnóstico/leitura documental: nunca entram aqui.
     */
    public const FONTES_OFICIAIS = ['senatran'];

    public function fonteOficial(string $provedor, ?string $declarada = null): bool
    {
        return $declarada === 'oficial' && in_array($provedor, self::FONTES_OFICIAIS, true);
    }

    public function avaliar(string $provedor, array $dados, array $contexto = []): array
    {
        $ambiente = ($contexto['ambiente'] ?? config('cnh.ambiente', 'homologacao')) === 'producao' ? 'producao' : 'homologacao';
        $hoje = isset($contexto['hoje']) ? Carbon::parse($contexto['hoje'])->startOfDay() : Carbon::now()->startOfDay();

        $categoria = $this->texto($dados['categoria'] ?? null, 10);
        $validadeTexto = $this->texto($dados['validade'] ?? null, 20);
        $validadeData = $this->data($validadeTexto);
        $situacao = $this->texto($dados['situacao'] ?? null, 100);
        $impedimentos = $dados['impedimentos'] ?? null;
        $documento = $this->texto($dados['documento_teste'] ?? ($dados['template'] ?? null), 80);

        $declarada = $this->declarada($contexto['autenticidade'] ?? null);
        $autenticidade = $this->autenticidade($provedor, $ambiente, $documento, $declarada);

        $divergencias = [];
        $validadeEstado = 'nao_informada';
        if ($validadeData) {
            $validadeEstado = $validadeData->lt($hoje) ? 'vencida' : 'vigente';
            if ($validadeEstado === 'vencida') {
                $divergencias[] = 'CNH com validade vencida em '.$validadeData->format('d/m/Y').'.';
            }
        }

        $esperadas = array_values(array_filter((array) ($contexto['categorias_veiculo'] ?? []), 'is_string'));
        $categoriaEstado = 'nao_informada';
        if ($categoria !== null) {
            $categoriaEstado = $esperadas ? ($this->categoriaCompativel($categoria, $esperadas) ? 'compativel' : 'incompativel') : 'informada_sem_comparacao';
            if ($categoriaEstado === 'incompativel') {
                $divergencias[] = 'Categoria '.$categoria.' incompatível com a categoria exigida ('.implode(', ', $esperadas).').';
            }
        }

        $situacaoEstado = 'nao_informada';
        if ($situacao !== null) {
            $situacaoEstado = match (true) {
                (bool) preg_match('/suspens|cassad|recolhid|bloquead|vencid|cancelad/i', $situacao) => 'irregular',
                (bool) preg_match('/ativ|regular|v[aá]lid|em dia|normal/i', $situacao) => 'regular',
                default => 'informada_sem_classificacao',
            };
            if ($situacaoEstado === 'irregular') {
                $divergencias[] = 'Situação informada indica restrição: '.$situacao.'.';
            }
        }

        $restricaoEstado = 'nao_informada';
        if ($impedimentos !== null) {
            $restricaoEstado = is_int($impedimentos) && $impedimentos > 0 ? 'com_impedimentos' : 'sem_impedimentos';
            if ($restricaoEstado === 'com_impedimentos') {
                $divergencias[] = 'Foram informadas '.$impedimentos.' ocorrência(s) de impedimento.';
            }
        }

        $estados = [$autenticidade === 'nao_verificada' ? 'nao_verificada' : 'ok', $validadeEstado, $categoriaEstado, $situacaoEstado, $restricaoEstado];
        $resultado = match (true) {
            $divergencias !== [] => 'divergente',
            in_array('nao_informada', $estados, true) => 'inconclusivo',
            default => 'conferido',
        };

        $liberacao = match (true) {
            $autenticidade === 'oficial' && $resultado === 'conferido' => 'producao',
            $ambiente === 'homologacao' => 'homologacao',
            default => 'nao_liberado',
        };

        return [
            'provedor' => $provedor,
            'ambiente' => $ambiente,
            'consultado_em' => Carbon::now()->toIso8601String(),
            'autenticidade' => $autenticidade,
            'garantia' => self::GARANTIAS[$autenticidade],
            'dimensoes' => [
                'autenticidade' => ['estado' => $autenticidade, 'valor' => null],
                'validade' => ['estado' => $validadeEstado, 'valor' => $validadeData?->format('Y-m-d') ?? $validadeTexto],
                'categoria' => ['estado' => $categoriaEstado, 'valor' => $categoria],
                'situacao' => ['estado' => $situacaoEstado, 'valor' => $situacao],
                'restricoes' => ['estado' => $restricaoEstado, 'valor' => $impedimentos],
            ],
            'divergencias' => $divergencias,
            'resultado' => $resultado,
            'liberacao' => $liberacao,
            'liberacao_motivo' => self::LIBERACOES[$liberacao],
        ];
    }

    private function autenticidade(string $provedor, string $ambiente, ?string $documento, ?string $declarada): string
    {
        if ($declarada === 'oficial') {
            if ($this->fonteOficial($provedor, $declarada)) {
                return $ambiente === 'producao' ? 'oficial' : 'oficial_homologacao';
            }

            // Provedor não autorizado a declarar oficialidade: rebaixa para documental.
            return $documento !== null ? 'documental' : 'nao_verificada';
        }

        if ($declarada === 'documental') {
            return 'documental';
        }

        if ($declarada === 'nao_verificada') {
            return 'nao_verificada';
        }

        // Sem declaração do adaptador: mantém o comportamento antigo, sempre fail-closed.
        return $documento !== null ? 'documental' : 'nao_verificada';
    }

    private function declarada(mixed $valor): ?string
    {
        return is_string($valor) && in_array($valor, ['oficial', 'documental', 'nao_verificada'], true) ? $valor : null;
    }

    private function categoriaCompativel(string $categoria, array $esperadas): bool
    {
        $normalizar = static fn (string $valor): string => strtoupper(preg_replace('/[^A-Za-z]/', '', $valor) ?? '');
        $informada = $normalizar($categoria);

        foreach ($esperadas as $esperada) {
            $exigida = $normalizar($esperada);
            if ($exigida === '') {
                continue;
            }
            if ($informada === $exigida || str_contains($informada, $exigida)) {
                return true;
            }
        }

        return false;
    }

    private function data(?string $valor): ?Carbon
    {
        if ($valor === null) {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s'] as $formato) {
            try {
                if (! Carbon::hasFormat($valor, $formato)) {
                    continue;
                }

                return Carbon::createFromFormat('!'.$formato, $valor)->startOfDay();
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($valor)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function texto(mixed $valor, int $limite): ?string
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        return mb_substr(trim($valor), 0, $limite);
    }
}
