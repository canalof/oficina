<?php

namespace App\Http\Controllers;

use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\OrdemServico;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Planilhas (CSV para Excel) para o contador ou para análise. */
class ExportacaoController extends Controller
{
    public function __invoke(Request $request, string $tipo)
    {
        $this->authorize('financeiro.ver');
        $m = (string) $request->query('mes', now()->format('Y-m'));
        $ini = preg_match('/^\d{4}-\d{2}$/', $m) ? Carbon::createFromFormat('Y-m-d', $m.'-01')->startOfDay() : now()->startOfMonth();
        $fim = $ini->copy()->endOfMonth();
        $r = fn (?int $c) => number_format(($c ?? 0) / 100, 2, ',', '');

        [$cabecalho, $linhas] = match ($tipo) {
            'os' => [['OS', 'Placa', 'Veículo', 'Cliente', 'Entregue em', 'Serviços', 'Peças', 'Desconto', 'Total', 'Custo das peças', 'Margem'],
                OrdemServico::with(['veiculo', 'cliente', 'itens.produto'])->where('status', 'entregue')->whereBetween('entregue_em', [$ini, $fim])->orderBy('entregue_em')->get()
                    ->map(function ($o) use ($r) {
                        $ap = $o->itens->where('situacao', 'aprovado');
                        $custo = (int) $ap->where('tipo', 'peca')->sum(fn ($i) => (int) round((float) $i->quantidade * (int) ($i->produto?->custo_centavos ?? 0)));

                        return [$o->numeroFormatado(), $o->veiculo->placaFormatada(), $o->veiculo->descricao(), $o->cliente?->nome, $o->entregue_em->format('d/m/Y'),
                            $r((int) $ap->where('tipo', 'servico')->sum('total_centavos')), $r((int) $ap->where('tipo', 'peca')->sum('total_centavos')),
                            $r($o->desconto_centavos), $r($o->total_centavos), $r($custo), $r($o->total_centavos - $custo)];
                    })],
            'receber' => [['Vencimento', 'Cliente', 'Descrição', 'Parcela', 'Valor', 'Situação', 'Recebido em', 'Forma'],
                ContaReceber::with('cliente')->where(fn ($q) => $q->whereBetween('vencimento', [$ini, $fim])->orWhereBetween('recebido_em', [$ini, $fim]))->orderBy('vencimento')->get()
                    ->map(fn ($c) => [$c->vencimento->format('d/m/Y'), $c->cliente?->nome, $c->descricao, $c->parcela, $r($c->valor_centavos), $c->status, $c->recebido_em?->format('d/m/Y'), $c->forma_recebida ?? $c->forma])],
            'pagar' => [['Vencimento', 'Fornecedor', 'Descrição', 'Categoria', 'Documento', 'Valor', 'Situação', 'Pago em', 'Forma'],
                ContaPagar::with('fornecedor')->where(fn ($q) => $q->whereBetween('vencimento', [$ini, $fim])->orWhereBetween('pago_em', [$ini, $fim]))->orderBy('vencimento')->get()
                    ->map(fn ($c) => [$c->vencimento->format('d/m/Y'), $c->fornecedor?->nome, $c->descricao, $c->categoria, $c->documento, $r($c->valor_centavos), $c->status, $c->pago_em?->format('d/m/Y'), $c->forma_paga])],
            default => abort(404),
        };

        return response()->streamDownload(function () use ($cabecalho, $linhas) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF"); // acentos certos no Excel
            fputcsv($f, $cabecalho, ';', '"', '');
            foreach ($linhas as $l) {
                fputcsv($f, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, $l), ';', '"', ''); // evita fórmula injetada
            }
            fclose($f);
        }, "torque-{$tipo}-{$ini->format('Y-m')}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
