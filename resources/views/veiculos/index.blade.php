<x-app titulo="Veículos">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1>Veículos</h1>
        @can('veiculos.editar')<a href="{{ route('recepcao') }}" class="btn-primario">Cadastrar pela placa</a>@endcan
    </div>

    <form class="mb-4 flex gap-2" role="search">
        <label class="sr-only" for="busca">Buscar veículo</label>
        <input id="busca" name="busca" value="{{ $busca }}" class="input max-w-md" placeholder="Placa, modelo, marca ou nome do dono">
        <button class="btn">Buscar</button>
        @if ($busca)<a href="{{ route('veiculos.index') }}" class="btn">Limpar</a>@endif
    </form>

    <div class="card overflow-x-auto">
        <table class="tabela">
            <thead><tr><th>Placa</th><th>Veículo</th><th class="hidden md:table-cell">Dono</th><th class="text-right">Km</th></tr></thead>
            <tbody>
            @forelse ($veiculos as $v)
                <tr>
                    <td class="w-32"><a href="{{ route('veiculos.show', $v) }}"><x-placa :placa="$v->placa" /></a></td>
                    <td><a class="font-semibold hover:underline" href="{{ route('veiculos.show', $v) }}">{{ $v->descricao() ?: 'Modelo não informado' }}</a><div class="text-sm text-suave md:hidden">{{ $v->cliente?->nome }}</div></td>
                    <td class="hidden md:table-cell">{{ $v->cliente?->nome ?? '—' }}</td>
                    <td class="whitespace-nowrap text-right">{{ $v->km_atual ? number_format($v->km_atual, 0, ',', '.') : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-10 text-center text-suave">
                    @if ($busca) Nenhum veículo encontrado para “{{ $busca }}”. @else Nenhum veículo cadastrado ainda. @endif
                </td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $veiculos->links() }}
    </div>
</x-app>
