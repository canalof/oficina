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
        $this->baixarEstoque($os);
        $this->gerarComissoes($os);
        $this->gerarLembretes($os);
    }

    private function baixarEstoque(OrdemServico $os): void
    {
        $permiteNegativo = (bool) (\App\Models\Empresa::whereKey($os->empresa_id)->value('estoque_negativo') ?? true);
        if (! $permiteNegativo) {
            $faltam = [];
            foreach ($os->itens->where('situacao', 'aprovado')->where('estoque_baixado', false)->whereNotNull('produto_id')->groupBy('produto_id') as $itens) {
                $p = $itens->first()->produto;
                $precisa = (float) $itens->sum('quantidade');
                if ($p && (float) $p->fresh()->estoque < $precisa) {
                    $faltam[] = $p->nome.' (tem '.\App\Models\Produto::formatarQtd($p->fresh()->estoque).', precisa '.\App\Models\Produto::formatarQtd($precisa).')';
                }
            }
            if ($faltam) {
                throw new \DomainException('Estoque insuficiente: '.implode('; ', $faltam).'. Dê entrada nas peças antes de entregar.');
            }
        }
        foreach ($os->itens as $item) {
            if ($item->situacao !== 'aprovado' || ! $item->produto_id || $item->estoque_baixado || ! $item->produto) {
                continue;
            }
            $item->produto->movimentar(-(float) $item->quantidade, 'saida', 'os', $os->id, $item->produto->custo_centavos, 'OS '.$os->numeroFormatado());
            $item->update(['estoque_baixado' => true]);
        }
    }

    private function gerarComissoes(OrdemServico $os): void
    {
        $sub = (int) $os->itens->where('situacao', 'aprovado')->sum('total_centavos');
        if ($sub === 0) {
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
            if ($item->situacao !== 'aprovado' || ! $s || (! $s->revisao_meses && ! $s->revisao_km)) {
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
