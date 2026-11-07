@php use App\Support\Dinheiro; use App\Models\Produto; $pode = auth()->user()->can('compras.lancar') && ! $compra->lancada(); @endphp
<x-app :titulo="'Nota '.$compra->numero">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1>Nota {{ $compra->numero }}{{ $compra->serie ? ' · série '.$compra->serie : '' }}</h1>
            <p class="text-suave">{{ $compra->fornecedor?->nome }} · {{ $compra->fornecedor?->documentoFormatado() }} · emitida em {{ $compra->emissao?->format('d/m/Y') }}</p>
            @if ($compra->chave)<p class="break-all text-xs text-suave">Chave {{ $compra->chave }}</p>@endif
        </div>
        <div class="flex gap-2">
            @if ($compra->xml_caminho)<a href="{{ route('compras.xml', $compra) }}" class="btn btn-sm">Baixar XML</a>@endif
            @if ($pode)
                <form method="post" action="{{ route('compras.destroy', $compra) }}" data-confirmar="Descartar esta importação?">@csrf @method('delete')<button class="btn-perigo btn-sm">Descartar</button></form>
            @endif
        </div>
    </div>

    <form method="post" action="{{ route('compras.lancar', $compra) }}">
        @csrf
        <section class="card mb-6 overflow-x-auto">
            <div class="border-b border-linha px-5 py-4">
                <h2>Produtos da nota</h2>
                @if ($pode)<p class="text-sm text-suave">Ligue cada item a um produto seu ou deixe "Cadastrar como novo". Na próxima nota deste fornecedor, o sistema já lembra.</p>@endif
            </div>
            <table class="tabela">
                <thead><tr><th>Na nota</th><th class="text-right">Qtd</th><th class="text-right">Custo</th><th>No meu estoque</th>@if($pode)<th class="text-right">Preço de venda</th>@endif</tr></thead>
                <tbody>
                @foreach ($compra->itens as $i)
                    <tr>
                        <td><div class="font-medium">{{ $i->descricao }}</div><div class="text-xs text-suave">cód. {{ $i->codigo_fornecedor }}{{ $i->codigo_barras ? ' · '.$i->codigo_barras : '' }}{{ $i->ncm ? ' · NCM '.$i->ncm : '' }}</div></td>
                        <td class="whitespace-nowrap text-right">{{ Produto::formatarQtd($i->quantidade) }} {{ $i->unidade }}</td>
                        <td class="whitespace-nowrap text-right">{{ Dinheiro::formatar($i->custo_unitario_centavos) }}</td>
                        <td class="min-w-[220px]">
                            @if ($pode)
                                <select name="produto[{{ $i->id }}]" class="input py-1.5 text-sm" aria-label="Produto para {{ $i->descricao }}">
                                    <option value="novo">Cadastrar como novo</option>
                                    <option value="ignorar">Não dar entrada (uso/consumo)</option>
                                    @foreach ($produtos as $p)
                                        <option value="{{ $p->id }}" @selected($i->produto_id === $p->id)>{{ $p->nome }}{{ $p->codigo ? ' ('.$p->codigo.')' : '' }}</option>
                                    @endforeach
                                </select>
                                @if ($i->produto_id)<div class="mt-1 text-xs text-ok">Reconhecido automaticamente</div>@endif
                            @else
                                {{ $i->produto ? $i->produto->nome : 'Sem entrada' }}
                            @endif
                        </td>
                        @if ($pode)
                            <td class="text-right"><input name="preco[{{ $i->id }}]" class="input w-28 py-1.5 text-right text-sm" data-mascara="dinheiro" inputmode="decimal" placeholder="{{ Dinheiro::campo($i->produto?->preco_centavos ?: (int) round($i->custo_unitario_centavos * 1.6)) }}" aria-label="Preço de venda"></td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="flex justify-end border-t border-linha px-5 py-3 text-lg font-semibold">Total da nota: {{ Dinheiro::formatar($compra->total_centavos) }}</div>
        </section>

        <section class="card card-corpo mb-6">
            <h2 class="mb-2">Pagamento ao fornecedor</h2>
            @if ($compra->duplicatas)
                <ul class="space-y-1 text-[15px]">
                    @foreach ($compra->duplicatas as $d)
                        <li>Parcela {{ $d['numero'] }}: <strong>{{ Dinheiro::formatar($d['valor_centavos']) }}</strong> vence {{ \Carbon\Carbon::parse($d['vencimento'])->format('d/m/Y') }}</li>
                    @endforeach
                </ul>
                <p class="mt-2 text-sm text-suave">Essas parcelas vão para Contas a pagar.</p>
            @elseif ($pode)
                <p class="mb-3 text-[15px] text-suave">A nota não traz parcelas. Se for pagar depois, informe o vencimento (vazio = já pago).</p>
                <x-campo nome="vencimento" rotulo="Vencimento" tipo="date" class="max-w-xs" />
            @else
                <p class="text-[15px] text-suave">Sem parcelas na nota.</p>
            @endif
        </section>

        @if ($pode)
            <button class="btn-primario px-6 py-3 text-base">Dar entrada no estoque</button>
        @else
            <p class="text-[15px] text-ok">Entrada feita em {{ $compra->lancada_em?->format('d/m/Y H:i') }}.</p>
        @endif
    </form>
</x-app>
