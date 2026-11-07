@php use App\Support\Dinheiro; use App\Models\Produto; @endphp
<x-app titulo="Produtos">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1>Produtos e estoque</h1>
            <p class="text-suave">Valor em estoque (pelo custo): {{ Dinheiro::formatar($valorEstoque) }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('auxiliar.index', 'categorias') }}" class="btn">Categorias</a>
            <a href="{{ route('auxiliar.index', 'fabricantes') }}" class="btn">Fabricantes</a>
            @can('estoque.editar')<a href="{{ route('importar', 'produtos') }}" class="btn">Importar</a>@endcan
            @can('estoque.editar')<a href="{{ route('exportar.cadastro', 'produtos') }}" class="btn">Exportar</a>@endcan
            @can('compras.lancar')<a href="{{ route('compras.index') }}" class="btn">Entrada por XML</a>@endcan
            @can('estoque.editar')<a href="{{ route('produtos.create') }}" class="btn-primario">Novo produto</a>@endcan
        </div>
    </div>

    @if ($qtdMinimo && $filtro !== 'minimo')
        <a href="{{ route('produtos.index', ['filtro' => 'minimo']) }}" class="mb-4 flex items-center justify-between gap-3 rounded-md border border-[#f0d58a] bg-[#fff7df] px-4 py-3 text-[15px] hover:border-sinal">
            <span><strong>{{ $qtdMinimo }} {{ $qtdMinimo === 1 ? 'produto está' : 'produtos estão' }}</strong> no estoque mínimo ou abaixo. Hora de comprar.</span>
            <span class="font-semibold">Ver lista</span>
        </a>
    @endif

    <form class="mb-4 flex flex-wrap gap-2" role="search">
        <label class="sr-only" for="busca">Buscar produto</label>
        <input id="busca" name="busca" value="{{ $busca }}" class="input max-w-sm" placeholder="Nome, código, código de barras ou marca">
        @if ($categorias->isNotEmpty())
            <select name="categoria" class="input w-auto" aria-label="Categoria"><option value="">Todas as categorias</option>@foreach($categorias as $c)<option value="{{ $c->id }}" @selected((string) $categoria === (string) $c->id)>{{ $c->nome }}</option>@endforeach</select>
        @endif
        <select name="filtro" class="input w-auto" aria-label="Filtro">
            <option value="">Todos</option>
            <option value="minimo" @selected($filtro === 'minimo')>No mínimo ou abaixo</option>
            <option value="negativo" @selected($filtro === 'negativo')>Estoque negativo</option>
        </select>
        <button class="btn">Buscar</button>
        @if ($busca || $filtro)<a href="{{ route('produtos.index') }}" class="btn">Limpar</a>@endif
    </form>

    <div class="card overflow-x-auto">
        <table class="tabela">
            <thead><tr><th>Produto</th><th class="hidden md:table-cell">Código</th><th class="hidden sm:table-cell">Local</th><th class="text-right">Estoque</th><th class="hidden text-right sm:table-cell">Mínimo</th><th class="text-right">Preço</th></tr></thead>
            <tbody>
            @forelse ($produtos as $p)
                <tr @class(['opacity-55' => ! $p->ativo])>
                    <td><a href="{{ route('produtos.show', $p) }}" class="font-semibold hover:underline">{{ $p->nome }}</a>@if($p->marcaNome())<div class="text-xs text-suave">{{ $p->marcaNome() }}{{ $p->referencia_fabricante ? ' · ref. '.$p->referencia_fabricante : '' }}</div>@endif</td>
                    <td class="hidden md:table-cell text-sm">{{ $p->codigo ?: $p->codigo_barras }}</td>
                    <td class="hidden sm:table-cell text-sm">{{ $p->localizacao }}</td>
                    <td @class(['whitespace-nowrap text-right font-semibold', 'text-erro' => (float) $p->estoque < 0, 'text-sinal-escuro' => $p->noMinimo() && (float) $p->estoque >= 0])>{{ $p->estoqueFormatado() }}</td>
                    <td class="hidden whitespace-nowrap text-right text-suave sm:table-cell">{{ (float) $p->estoque_minimo ? Produto::formatarQtd($p->estoque_minimo) : '—' }}</td>
                    <td class="whitespace-nowrap text-right">{{ Dinheiro::formatar($p->preco_centavos) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-10 text-center text-suave">Nenhum produto. Cadastre manualmente ou importe o XML de uma nota de compra.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $produtos->links() }}
    </div>
</x-app>
