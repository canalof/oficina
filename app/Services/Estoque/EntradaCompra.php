<?php

namespace App\Services\Estoque;

use App\Models\Compra;
use App\Models\ContaPagar;
use App\Models\Fornecedor;
use App\Models\Produto;
use App\Models\ProdutoFornecedorCodigo;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EntradaCompra
{
    public function __construct(private LeitorXmlNfe $leitor) {}

    /** Lê o XML e cria a compra em rascunho, já ligando os itens que o sistema reconhece. */
    public function importar(string $xml): Compra
    {
        $d = $this->leitor->ler($xml);

        if ($d['chave'] && ($existe = Compra::where('chave', $d['chave'])->first())) {
            throw new DomainException('Esta nota já foi importada (nº '.$existe->numero.').');
        }

        return DB::transaction(function () use ($d, $xml) {
            $fornecedor = null;
            if ($d['fornecedor']['cpf_cnpj']) {
                $fornecedor = Fornecedor::firstOrCreate(
                    ['cpf_cnpj' => $d['fornecedor']['cpf_cnpj']],
                    array_filter($d['fornecedor']),
                );
            }

            $compra = Compra::create([
                'fornecedor_id' => $fornecedor?->id,
                'user_id' => auth()->id(),
                'numero' => $d['numero'],
                'serie' => $d['serie'],
                'chave' => $d['chave'],
                'emissao' => $d['emissao'],
                'total_centavos' => $d['total_centavos'],
                'duplicatas' => $d['duplicatas'],
                'status' => 'rascunho',
            ]);

            $caminho = 'compras/'.$compra->empresa_id.'/'.($d['chave'] ?: 'compra-'.$compra->id).'.xml';
            Storage::disk('local')->put($caminho, $xml);
            $compra->update(['xml_caminho' => $caminho]);

            foreach ($d['itens'] as $i) {
                $compra->itens()->create($i + ['produto_id' => $this->reconhecer($fornecedor, $i)?->id]);
            }

            return $compra;
        });
    }

    /** Procura pelo código do fornecedor já aprendido e depois pelo código de barras. */
    public function reconhecer(?Fornecedor $fornecedor, array $item): ?Produto
    {
        if ($fornecedor && $item['codigo_fornecedor']) {
            $id = ProdutoFornecedorCodigo::where('fornecedor_id', $fornecedor->id)->where('codigo', $item['codigo_fornecedor'])->value('produto_id');
            if ($id && ($p = Produto::find($id))) {
                return $p;
            }
        }
        if ($item['codigo_barras']) {
            return Produto::where('codigo_barras', $item['codigo_barras'])->first();
        }

        return null;
    }

    /**
     * Dá entrada no estoque.
     *
     * @param  array<int,string>  $ligacoes  item_id => produto_id | "novo"
     * @param  array<int,int>  $precos  item_id => preço de venda em centavos (para produtos novos ou para atualizar)
     */
    public function lancar(Compra $compra, array $ligacoes, array $precos = [], ?string $vencimentoUnico = null): void
    {
        if ($compra->lancada()) {
            throw new DomainException('Esta nota já foi lançada no estoque.');
        }

        DB::transaction(function () use ($compra, $ligacoes, $precos, $vencimentoUnico) {
            $compra->load('itens', 'fornecedor');

            foreach ($compra->itens as $item) {
                $escolha = $ligacoes[$item->id] ?? ($item->produto_id ?: 'novo');
                if ($escolha === 'ignorar') {
                    continue;
                }
                $produto = $escolha === 'novo'
                    ? Produto::create([
                        'nome' => $item->descricao,
                        'codigo_barras' => $item->codigo_barras,
                        'ncm' => $item->ncm,
                        'unidade' => $item->unidade ?: 'UN',
                        'custo_centavos' => $item->custo_unitario_centavos,
                        'preco_centavos' => $precos[$item->id] ?? (int) round($item->custo_unitario_centavos * 1.6),
                    ])
                    : Produto::findOrFail((int) $escolha);

                $produto->movimentar((float) $item->quantidade, 'entrada', 'compra', $compra->id, $item->custo_unitario_centavos, 'NF '.$compra->numero.' '.($compra->fornecedor?->nome ?? ''));

                $atualizar = [];
                if (! $produto->ncm && $item->ncm) {
                    $atualizar['ncm'] = $item->ncm;
                }
                if (! $produto->codigo_barras && $item->codigo_barras) {
                    $atualizar['codigo_barras'] = $item->codigo_barras;
                }
                if (isset($precos[$item->id]) && $precos[$item->id] > 0) {
                    $atualizar['preco_centavos'] = $precos[$item->id];
                }
                if ($atualizar) {
                    $produto->update($atualizar);
                }
                $item->update(['produto_id' => $produto->id]);

                // Aprende: da próxima vez, o código deste fornecedor já vem ligado
                if ($compra->fornecedor_id && $item->codigo_fornecedor) {
                    ProdutoFornecedorCodigo::updateOrCreate(
                        ['fornecedor_id' => $compra->fornecedor_id, 'codigo' => $item->codigo_fornecedor],
                        ['produto_id' => $produto->id],
                    );
                }
            }

            $parcelas = $compra->duplicatas ?: [];
            if (! $parcelas && $vencimentoUnico) {
                $parcelas = [['numero' => '1', 'vencimento' => $vencimentoUnico, 'valor_centavos' => $compra->total_centavos]];
            }
            $n = count($parcelas);
            foreach (array_values($parcelas) as $i => $dup) {
                ContaPagar::create([
                    'fornecedor_id' => $compra->fornecedor_id,
                    'compra_id' => $compra->id,
                    'categoria' => 'Peças e fornecedores',
                    'descricao' => 'NF '.$compra->numero.' '.($compra->fornecedor?->nome ?? '').($n > 1 ? ' ('.($i + 1).'/'.$n.')' : ''),
                    'documento' => $dup['numero'] ?: null,
                    'valor_centavos' => $dup['valor_centavos'],
                    'vencimento' => $dup['vencimento'] ?: today()->addDays(30)->toDateString(),
                ]);
            }

            $compra->update(['status' => 'lancada', 'lancada_em' => now()]);
        });
    }
}
