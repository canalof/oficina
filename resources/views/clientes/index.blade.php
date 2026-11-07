<x-app titulo="Clientes">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1>Clientes</h1>
        <div class="flex flex-wrap gap-2">
            @can('clientes.editar')<a href="{{ route('importar', 'clientes') }}" class="btn">Importar</a>@endcan
            @can('clientes.editar')<a href="{{ route('exportar.cadastro', 'clientes') }}" class="btn">Exportar</a>@endcan
            @can('clientes.editar')<a href="{{ route('clientes.create') }}" class="btn-primario">Novo cliente</a>@endcan
        </div>
    </div>

    <form class="mb-4 flex gap-2" role="search">
        <label class="sr-only" for="busca">Buscar cliente</label>
        <input id="busca" name="busca" value="{{ $busca }}" class="input max-w-md" placeholder="Nome, CPF/CNPJ, telefone ou e-mail">
        <button class="btn">Buscar</button>
        @if ($busca)<a href="{{ route('clientes.index') }}" class="btn">Limpar</a>@endif
    </form>

    <div class="card overflow-x-auto">
        <table class="tabela">
            <thead><tr><th>Nome</th><th class="hidden md:table-cell">CPF/CNPJ</th><th>Telefone</th><th class="hidden sm:table-cell">Cidade</th><th class="text-right">Carros</th></tr></thead>
            <tbody>
            @forelse ($clientes as $c)
                <tr>
                    <td><a class="font-semibold hover:underline" href="{{ route('clientes.show', $c) }}">{{ $c->nome }}</a></td>
                    <td class="hidden whitespace-nowrap md:table-cell">{{ $c->documentoFormatado() }}</td>
                    <td class="whitespace-nowrap">{{ $c->whatsapp ?: $c->telefone }}</td>
                    <td class="hidden sm:table-cell">{{ $c->cidade }}</td>
                    <td class="text-right">{{ $c->veiculos_count }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-10 text-center text-suave">
                    @if ($busca) Nenhum cliente encontrado para “{{ $busca }}”. @else Nenhum cliente cadastrado ainda. @endif
                </td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $clientes->links() }}
    </div>
</x-app>
