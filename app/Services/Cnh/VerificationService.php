<?php

namespace App\Services\Cnh;

use App\Models\ConferenciaCnh;
use App\Models\Empresa;
use App\Models\MobilidadeCondutor;
use App\Models\MobilidadeRetirada;
use App\Models\OrdemServico;
use DomainException;

class VerificationService
{
    public function evidencias(MobilidadeCondutor $condutor, bool $dadosConferidos, bool $categoriaCompativel): array
    {
        return [
            'dados_conferidos' => $dadosConferidos,
            'validade_aparente' => $condutor->validade ? ($condutor->validade->lt(today()) ? 'vencida' : 'na_data') : 'nao_informada',
            'categoria_informada' => $condutor->categoria,
            'categoria_compativel' => $categoriaCompativel && filled($condutor->categoria),
            'autenticidade' => 'nao_verificada',
            'situacao_atual' => 'nao_verificada',
        ];
    }

    public function verificar(Empresa $empresa, MobilidadeCondutor $condutor, ?ConferenciaCnh $conferencia, ?string $excecao = null): void
    {
        $evidencias = $conferencia?->evidencias ?? [];

        if ($empresa->mobilidade_bloqueia_divergencia && $conferencia?->resultado === 'divergente' && ! $excecao) {
            throw new DomainException('A conferência do condutor apontou divergência.');
        }

        if ($empresa->mobilidade_exige_oficial && ! $excecao) {
            $this->exigirConferenciaOficial($conferencia, $evidencias, $condutor);
        }

        if (! $empresa->mobilidade_exige_cnh || $excecao) {
            return;
        }
        if ($conferencia?->resultado !== 'conferido' || $conferencia->created_at->lt(now()->subDay())
            || $condutor->updated_at->gt($conferencia->created_at)
            || ! $condutor->validade || $condutor->validade->lt(today())
            || ! ($evidencias['dados_conferidos'] ?? false) || ! ($evidencias['categoria_compativel'] ?? false)) {
            throw new DomainException('Registre uma conferência recente com dados conferidos, validade e categoria compatível.');
        }
    }

    /**
     * Caminho real da exigência oficial: precisa de conferência recente cujo
     * resultado venha de consulta oficial em produção. Se o ambiente estiver em
     * homologação (ou sem endpoint oficial), a liberação é recusada com o motivo.
     */
    private function exigirConferenciaOficial(?ConferenciaCnh $conferencia, array $evidencias, MobilidadeCondutor $condutor): void
    {
        if (! $conferencia || $conferencia->created_at->lt(now()->subDay())) {
            throw new DomainException('Esta oficina exige consulta oficial recente (menos de 24 h). Registre a conferência em Condutores e CNH.');
        }

        $liberacao = $evidencias['liberacao'] ?? null;
        $autenticidade = $evidencias['autenticidade'] ?? ($evidencias['dimensoes']['autenticidade']['estado'] ?? null);

        if ($liberacao !== 'producao' || $autenticidade !== 'oficial') {
            $ambiente = ($evidencias['ambiente'] ?? config('cnh.ambiente', 'homologacao')) === 'producao' ? 'produção' : 'homologação';
            throw new DomainException('Esta oficina exige consulta oficial em produção e a última conferência foi registrada em '.$ambiente.'. Ative o credenciamento oficial (CNH_AMBIENTE=producao e endpoint do órgão) para liberar a retirada.');
        }
        if ($conferencia->resultado !== 'conferido') {
            throw new DomainException('A consulta oficial não confirmou a CNH do condutor.');
        }
        if ($condutor->updated_at->gt($conferencia->created_at)) {
            throw new DomainException('Os dados do condutor mudaram depois da consulta oficial. Refaça a consulta.');
        }
    }

    public function concluirRetirada(OrdemServico $os): void
    {
        $empresa = Empresa::findOrFail($os->empresa_id);
        $retirada = MobilidadeRetirada::where('ordem_servico_id', $os->id)->whereNull('entregue_em')->latest('id')->first();
        if (! $retirada) {
            if ($empresa->mobilidade_exige_cnh || $empresa->mobilidade_exige_oficial) {
                throw new DomainException('Registre a autorização de retirada no módulo Mobilidade antes de entregar a OS.');
            }

            return;
        }
        $condutor = MobilidadeCondutor::find($retirada->condutor_id);
        if (! $condutor) {
            throw new DomainException('O condutor autorizado está inativo. Revise a retirada.');
        }
        $conferencia = ConferenciaCnh::where('condutor_id', $condutor->id)
            ->where('ordem_servico_id', $os->id)->where('finalidade', 'retirada')->latest('id')->first();
        $this->verificar($empresa, $condutor, $conferencia, $retirada->justificativa);
        $retirada->update(['entregue_em' => now(), 'conferencia_id' => $conferencia?->id, 'user_id' => auth()->id()]);
        $os->registrar('Retirada registrada no módulo Mobilidade (#'.$retirada->id.').');
    }
}
