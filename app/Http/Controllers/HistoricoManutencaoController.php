<?php

namespace App\Http\Controllers;

use App\Models\AutocenterServico;
use App\Models\OrdemServico;
use App\Models\PneuInspecao;
use App\Models\Veiculo;
use App\Support\CsvSeguro;
use Illuminate\Support\Collection;

class HistoricoManutencaoController extends Controller
{
    public function __invoke(Veiculo $veiculo)
    {
        $this->authorize('veiculos.ver');
        $historico = $this->eventos($veiculo);

        return view('veiculos.historico-manutencao', compact('veiculo', 'historico'));
    }

    /** Exportação limitada ao mesmo conjunto de eventos exibido na tela. */
    public function exportarCsv(Veiculo $veiculo)
    {
        $this->authorize('veiculos.ver');
        $historico = $this->eventos($veiculo);
        $arquivo = 'historico-manutencao-'.preg_replace('/[^A-Za-z0-9-]/', '', $veiculo->placa).'.csv';

        return response()->streamDownload(function () use ($historico) {
            $saida = fopen('php://output', 'wb');
            fwrite($saida, "\xEF\xBB\xBF"); // Excel em UTF-8
            fputcsv($saida, ['Data', 'Origem', 'Descrição', 'OS', 'KM', 'Observações'], ';');
            foreach ($historico as $evento) {
                $valores = [
                    $evento['data']?->format('d/m/Y H:i') ?? '',
                    $evento['tipo'], $evento['descricao'],
                    $evento['os'] ?? '', $evento['km'] ?? '',
                    $evento['observacoes'] ?? '',
                ];
                // Impede interpretação de textos cadastrados como fórmulas em planilhas.
                $valores = array_map([self::class, 'celulaCsvSegura'], $valores);
                fputcsv($saida, $valores, ';');
            }
            fclose($saida);
        }, $arquivo, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    /**
     * Neutraliza fórmulas em células de planilhas, inclusive após espaços Unicode,
     * BOM e controles. Não altera números de colunas calculadas pela aplicação.
     */
    public static function celulaCsvSegura(mixed $valor): string
    {
        return (string) CsvSeguro::celula((string) $valor);
    }

    private function eventos(Veiculo $veiculo): Collection
    {
        // Each model carries the tenant global scope. Limit every query before merging.
        $servicos = AutocenterServico::query()->where('veiculo_id', $veiculo->id)
            ->with('ordemServico:id,numero')->latest('id')->limit(100)->get()
            ->map(fn ($s) => [
                'data' => $s->created_at, 'tipo' => 'Autocenter',
                'descricao' => str_replace('_', ' ', $s->tipo),
                'km' => $s->km_registrado, 'os' => $s->ordemServico?->numero,
                'observacoes' => $s->observacoes, 'ordem' => $s->id,
            ]);
        $pneus = PneuInspecao::query()->where('veiculo_id', $veiculo->id)
            ->latest('id')->limit(100)->get()->map(fn ($p) => [
                'data' => $p->created_at, 'tipo' => 'Pneu',
                'descricao' => str_replace('_', ' ', $p->posicao).($p->medida ? ' — '.$p->medida : ''),
                'km' => $p->km_registrado, 'os' => null,
                'observacoes' => $p->observacoes, 'ordem' => $p->id,
            ]);
        $ordens = OrdemServico::query()->where('veiculo_id', $veiculo->id)
            ->latest('id')->limit(100)->get()->map(fn ($o) => [
                'data' => $o->created_at, 'tipo' => 'Ordem de serviço',
                'descricao' => 'OS #'.$o->numero,
                'km' => $o->km_entrada, 'os' => $o->numero,
                'observacoes' => $o->defeito_relatado, 'ordem' => $o->id,
            ]);

        $historico = Collection::make()->concat($servicos)->concat($pneus)->concat($ordens)
            ->sortByDesc(fn ($item) => ($item['data']?->format('Y-m-d H:i:s.u') ?? '').sprintf('%010d', $item['ordem']))
            ->take(150)->values();

        return $historico;
    }
}
