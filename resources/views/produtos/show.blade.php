@php use App\Support\Dinheiro; use App\Models\Produto; @endphp
<x-app :titulo="$produto->nome">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1>{{ $produto->nome }}</h1>
            <p class="text-suave">{{ collect([$produto->marca, $produto->codigo ? 'Cód. '.$produto->codigo : null, $produto->codigo_barras, $produto->ncm ? 'NCM '.$produto->ncm : 'Sem NCM'])->filter()->join(' · ') }}</p>
        </div>
        @can('estoque.editar')<a href="{{ route('produtos.edit', $produto) }}" class="btn">Editar</a>@endcan
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-4">
        <div class="card card-corpo"><div class="text-sm text-suave">Em estoque</div><div @class(['mt-1 text-3xl font-semibold', 'text-erro' => (float) $produto->estoque < 0, 'text-sinal-escuro' => $produto->noMinimo()])>{{ $produto->estoqueFormatado() }}</div><div class="text-sm text-suave">reservado em OS: {{ Produto::formatarQtd($produto->reservado()) }} · mínimo {{ Produto::formatarQtd($produto->estoque_minimo) }}</div></div>
        <div class="card card-corpo"><div class="text-sm text-suave">Custo</div><div class="mt-1 text-3xl font-semibold">{{ Dinheiro::formatar($produto->custo_centavos) }}</div></div>
        <div class="card card-corpo"><div class="text-sm text-suave">Preço de venda</div><div class="mt-1 text-3xl font-semibold">{{ Dinheiro::formatar($produto->preco_centavos) }}</div>
            @if ($produto->custo_centavos)<div class="text-sm text-suave">margem {{ number_format(($produto->preco_centavos - $produto->custo_centavos) / max(1, $produto->preco_centavos) * 100, 0) }}%</div>@endif</div>
        <div class="card card-corpo"><div class="text-sm text-suave">Onde fica</div><div class="mt-1 text-xl font-semibold">{{ $produto->localizacao ?: '—' }}</div></div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1.6fr_1fr] [&>*]:min-w-0">
        <section class="card overflow-x-auto">
            <div class="border-b border-linha px-5 py-4"><h2>Movimentações</h2></div>
            <table class="tabela">
                <thead><tr><th>Quando</th><th>Origem</th><th class="text-right">Qtd</th><th class="text-right">Saldo</th></tr></thead>
                <tbody>
                @forelse ($movimentos as $m)
                    <tr>
                        <td class="whitespace-nowrap text-sm">{{ $m->created_at->format('d/m/Y H:i') }}<div class="text-xs text-suave">{{ $m->user?->name }}</div></td>
                        <td class="text-sm">{{ $m->origemTexto() }}@if($m->observacao)<div class="text-xs text-suave">{{ $m->observacao }}</div>@endif</td>
                        <td @class(['whitespace-nowrap text-right font-semibold', 'text-ok' => (float) $m->quantidade > 0, 'text-erro' => (float) $m->quantidade < 0])>{{ (float) $m->quantidade > 0 ? '+' : '' }}{{ Produto::formatarQtd($m->quantidade) }}</td>
                        <td class="whitespace-nowrap text-right">{{ Produto::formatarQtd($m->saldo) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-suave">Sem movimentações.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>

        @can('estoque.editar')
            <section class="card card-corpo h-fit">
                <h2 class="mb-3">Mexer no estoque</h2>
                <form method="post" action="{{ route('produtos.movimentar', $produto) }}" class="space-y-3">
                    @csrf
                    <label class="campo"><span>O que aconteceu</span>
                        <select name="tipo" class="input">
                            <option value="entrada">Entrou mercadoria (sem nota)</option>
                            <option value="saida">Saiu (perda, uso interno)</option>
                            <option value="contagem">Contei a prateleira</option>
                        </select>
                    </label>
                    <x-campo nome="quantidade" rotulo="Quantidade" inputmode="decimal" required ajuda="Na contagem, informe quanto tem de verdade." />
                    <x-campo nome="observacao" rotulo="Observação" />
                    <button class="btn-primario">Registrar</button>
                </form>
            </section>
        @endcan
    </div>
</x-app>
