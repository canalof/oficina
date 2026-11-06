<x-app titulo="Fornecedores">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1>Fornecedores</h1>
        @can('compras.lancar')<a href="{{ route('fornecedores.create') }}" class="btn-primario">Novo fornecedor</a>@endcan
    </div>
    <form class="mb-4 flex gap-2" role="search">
        <input name="busca" value="{{ $busca }}" class="input max-w-sm" placeholder="Buscar fornecedor" aria-label="Buscar fornecedor">
        <button class="btn">Buscar</button>
    </form>
    <div class="card overflow-x-auto">
        <table class="tabela">
            <thead><tr><th>Fornecedor</th><th class="hidden md:table-cell">CNPJ</th><th>Telefone</th><th class="hidden sm:table-cell">Cidade</th><th class="text-right">Notas</th></tr></thead>
            <tbody>
            @forelse ($fornecedores as $f)
                <tr>
                    <td>@can('compras.lancar')<a href="{{ route('fornecedores.edit', $f) }}" class="font-semibold hover:underline">{{ $f->nome }}</a>@else<span class="font-semibold">{{ $f->nome }}</span>@endcan
                        @if($f->contato)<div class="text-xs text-suave">{{ $f->contato }}</div>@endif</td>
                    <td class="hidden whitespace-nowrap md:table-cell">{{ $f->documentoFormatado() }}</td>
                    <td class="whitespace-nowrap">{{ $f->telefone }}</td>
                    <td class="hidden sm:table-cell">{{ collect([$f->cidade, $f->uf])->filter()->join('/') }}</td>
                    <td class="text-right">{{ $f->compras_count }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-10 text-center text-suave">Nenhum fornecedor. Eles são cadastrados sozinhos quando você importa o XML de uma nota.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $fornecedores->links() }}
    </div>
</x-app>
