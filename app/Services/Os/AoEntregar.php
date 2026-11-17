<?php

namespace App\Services\Os;

use App\Models\Comissao;
use App\Models\Lembrete;
use App\Models\OrdemServico;
use App\Models\User;

/**
 * O que acontece quando o carro é entregue:
 * 1. baixa no estoque das peças aprovadas;
 * 2. comissão dos mecânicos sobre os serviços;
 * 3. lembretes de revisão (ex.: troca de óleo em 6 meses).
 */
class AoEntregar
{
    public function executar(OrdemServico $os): void
    {
        $os->load(['itens.produto', 'itens.servico', 'veiculo']);
        $this->validarPecasDaEntrega($os);
        $this->baixarEstoque($os);
        $this->gerarComissoes($os);
        $this->gerarLembretes($os);
    }

    /**
     * A entrega não pode concluir parcialmente quando uma peça aprovada foi
     * excluída, está inacessível ou possui quantidade inválida. A exceção
     * reverte a transação da OS e qualquer lançamento anterior.
     */
    private function validarPecasDaEntrega(OrdemServico $os): void
    {
        foreach ($os->itens as $item) {
            if ($item->situacao !== 'aprovado' || $item->estoque_baixado || $item->tipo !== 'peca') {
                continue;
            }
            if (! $item->produto_id || ! $item->produto) {
                throw new \DomainException('Peça aprovada não encontrada no estoque. Regularize o cadastro antes da entrega.');
            }
            $quantidade = (float) $item->quantidade;
            if (! is_finite($quantidade) || $quantidade <= 0) {
                throw new \DomainException('Peça aprovada com quantidade inválida. Corrija a OS antes da entrega.');
            }
        }
    }

    private function baixarEstoque(OrdemServico $os): void
    {
        // Bloqueia os produtos em ordem estável para reduzir deadlocks entre
        // entregas simultâneas que utilizam as mesmas peças.
        $pendentes = $os->itens->filter(fn ($item) =>
            $item->situacao === 'aprovado' && $item->tipo === 'peca'
            && ! $item->estoque_baixado
        );
        if ($pendentes->isEmpty()) {
            return;
        }

        $ids = $pendentes->pluck('produto_id')->unique()->sort()->values();
        $produtos = \App\Models\Produto::whereIn('id', $ids->all())
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        $necessario = [];
        foreach ($pendentes as $item) {
            $produto = $produtos->get($item->produto_id);
            if (! $produto) {
                throw new \DomainException('Peça aprovada não encontrada nesta oficina. Regularize o cadastro antes da entrega.');
            }
            $qtd = (float) $item->quantidade;
            if (! is_finite($qtd) || $qtd <= 0 || abs($qtd - round($qtd, 3)) > 0.0000001) {
                throw new \DomainException('Quantidade de peça inválida para entrega.');
            }
            $necessario[$produto->id] = ($necessario[$produto->id] ?? 0) + $qtd;
        }

        // Falha fechada: ausência da configuração não autoriza estoque negativo.
        $permiteNegativo = \App\Models\Empresa::whereKey($os->empresa_id)
            ->where('estoque_negativo', true)->exists();
        if (! $permiteNegativo) {
            $faltam = [];
            foreach ($necessario as $produtoId => $qtd) {
                $produto = $produtos->get($produtoId);
                if (round((float) $produto->estoque - $qtd, 3) < 0) {
                    $faltam[] = $produto->nome.' (tem '.\App\Models\Produto::formatarQtd($produto->estoque)
                        .', precisa '.\App\Models\Produto::formatarQtd($qtd).')';
                }
            }
            if ($faltam) {
                throw new \DomainException('Estoque insuficiente: '.implode('; ', $faltam).'. Dê entrada nas peças antes de entregar.');
            }
        }

        // O chamador executa a entrega em transação. Movimentar também usa
        // transação aninhada; qualquer falha reverte todas as peças e a OS.
        foreach ($pendentes as $item) {
            $produto = $produtos->get($item->produto_id);
            $produto->movimentar(-(float) $item->quantidade, 'saida', 'os', $os->id,
                $produto->custo_centavos, 'OS '.$os->numeroFormatado());
            $item->update(['estoque_baixado' => true]);
        }
    }

    private function gerarComissoes(OrdemServico $os): void
    {
        $sub = (int) $os->itens->where('situacao', 'aprovado')->sum('total_centavos');
        if ($sub <= 0) {
            // Subtotal nulo/negativo não pode originar comissão.
            return;
        }
        $fator = $os->total_centavos / $sub; // aplica o desconto proporcionalmente

        $porMecanico = [];
        foreach ($os->itens as $item) {
            if ($item->situacao !== 'aprovado' || $item->tipo !== 'servico') {
                continue;
            }
            $quem = $item->mecanico_id ?: $os->mecanico_id;
            if ($quem) {
                $porMecanico[$quem] = ($porMecanico[$quem] ?? 0) + (int) round($item->total_centavos * $fator);
            }
        }

        foreach ($porMecanico as $userId => $base) {
            $u = User::where('empresa_id', $os->empresa_id)->find($userId);
            $pct = (float) ($u?->comissao_percentual ?? 0);
            if ($pct <= 0 || $base <= 0) {
                continue;
            }
            Comissao::updateOrCreate(
                ['ordem_servico_id' => $os->id, 'user_id' => $userId],
                ['base_centavos' => $base, 'percentual' => $pct, 'valor_centavos' => (int) round($base * $pct / 100), 'competencia' => today()->startOfMonth()],
            );
        }
    }

    private function gerarLembretes(OrdemServico $os): void
    {
        foreach ($os->itens as $item) {
            $s = $item->servico;
            if ($item->situacao !== 'aprovado' || $item->tipo !== 'servico' || ! $s || (! $s->revisao_meses && ! $s->revisao_km)) {
                continue;
            }
            // Substitui o lembrete antigo do mesmo serviço para este carro
            Lembrete::where('veiculo_id', $os->veiculo_id)->where('descricao', $s->nome)->where('status', 'pendente')->update(['status' => 'descartado']);
            Lembrete::create([
                'veiculo_id' => $os->veiculo_id,
                'cliente_id' => $os->cliente_id,
                'ordem_servico_id' => $os->id,
                'descricao' => $s->nome,
                'data_prevista' => today()->addMonths($s->revisao_meses ?: 6),
                'km_previsto' => $s->revisao_km && $os->km_entrada ? $os->km_entrada + $s->revisao_km : null,
            ]);
        }
    }
}
