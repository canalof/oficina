<x-app :titulo="$titulo">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1>{{ $titulo }}</h1>
        <a href="{{ route('produtos.index') }}" class="btn">Voltar aos produtos</a>
    </div>
    @can('estoque.editar')
        <form method="post" action="{{ route('auxiliar.store', $tipo) }}" class="card card-corpo mb-5 flex max-w-xl flex-wrap items-end gap-3">
            @csrf
            <x-campo nome="nome" rotulo="Novo nome" required maxlength="80" class="min-w-[220px] flex-1" />
            <button class="btn-primario">Cadastrar</button>
        </form>
    @endcan
    <div class="card max-w-xl divide-y divide-linha">
        @forelse ($itens as $i)
            <div @class(['flex flex-wrap items-center gap-3 px-5 py-3', 'opacity-60' => ! $i->ativo])>
                @can('estoque.editar')
                    <form method="post" action="{{ route('auxiliar.update', [$tipo, $i->id]) }}" class="flex flex-1 flex-wrap items-center gap-2">
                        @csrf @method('put')
                        <input name="nome" value="{{ $i->nome }}" class="input min-w-[160px] flex-1 py-1.5" aria-label="Nome" required maxlength="80">
                        <label class="flex items-center gap-1 text-sm"><input type="hidden" name="ativo" value="0"><input type="checkbox" name="ativo" value="1" class="h-4 w-4 accent-asfalto" @checked($i->ativo)> ativo</label>
                        <button class="btn btn-sm">Salvar</button>
                    </form>
                @else
                    <span class="flex-1">{{ $i->nome }}</span>
                @endcan
                <span class="text-sm text-suave">{{ $i->produtos_count }} produto(s)</span>
            </div>
        @empty
            <p class="px-5 py-8 text-center text-suave">Nada cadastrado ainda.</p>
        @endforelse
    </div>
</x-app>
