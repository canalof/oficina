<x-app titulo="Modelos de OS">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1>Modelos de OS</h1>
            <p class="text-suave">Salve combinações de serviços e peças que sua oficina usa com frequência.</p>
        </div>
        <a class="btn" href="{{ route('servicos.index') }}">Voltar ao catálogo</a>
    </div>
    <div class="grid gap-5 lg:grid-cols-2">
        <div class="card card-corpo">
            <h2 class="mb-3">Modelos cadastrados</h2>
            <ul class="divide-y divide-linha">
                @forelse ($modelos as $modelo)
                    <li class="flex items-center justify-between gap-3 py-3">
                        <div>
                            <p class="font-semibold">{{ $modelo->nome }}</p>
                            <p class="text-sm text-suave">{{ count($modelo->servico_ids) }} serviço(s) · {{ count($modelo->produto_ids) }} peça(s){{ $modelo->ativo ? '' : ' · Inativo' }}</p>
                        </div>
                        <a class="btn btn-sm" href="{{ route('modelos-os.index', ['editar' => $modelo->id]) }}">Editar</a>
                    </li>
                @empty
                    <li class="text-suave">Nenhum modelo cadastrado.</li>
                @endforelse
            </ul>
            {{ $modelos->links() }}
        </div>
        <form method="post" action="{{ $editando ? route('modelos-os.update', $editando) : route('modelos-os.store') }}" class="card card-corpo space-y-4">
            @csrf @if ($editando) @method('put') @endif
            <h2>{{ $editando ? 'Editar modelo' : 'Novo modelo' }}</h2>
            <x-campo nome="nome" rotulo="Nome do modelo" :valor="$editando?->nome" required maxlength="120" placeholder="Ex.: Troca de óleo + filtro" />
            @if ($errors->any())<p class="erro-msg" role="alert">{{ $errors->first() }}</p>@endif
            @foreach (['servico_ids' => ['Serviços', $servicos], 'produto_ids' => ['Peças e produtos', $produtos]] as $campo => [$rotulo, $opcoes])
                <fieldset>
                    <legend class="mb-1 font-semibold">{{ $rotulo }}</legend>
                    <div class="max-h-48 space-y-2 overflow-y-auto rounded-md border border-linha p-3">
                        @forelse ($opcoes as $item)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="{{ $campo }}[]" value="{{ $item->id }}" @checked(in_array((string) $item->id, array_map('strval', (array) old($campo, $editando?->{$campo} ?? [])), true))>
                                {{ $item->nome }}{{ $campo === 'produto_ids' && $item->codigo ? ' · '.$item->codigo : '' }}
                            </label>
                        @empty
                            <p class="text-sm text-suave">Nenhum item ativo. Cadastre no catálogo ou no estoque.</p>
                        @endforelse
                    </div>
                </fieldset>
            @endforeach
            @if ($editando)
                <label class="flex items-center gap-2 text-sm"><input type="hidden" name="ativo" value="0"><input type="checkbox" name="ativo" value="1" @checked(old('ativo', $editando->ativo))> Disponível para aplicar em OS</label>
            @endif
            <p class="text-sm text-suave">Ao aplicar, cada item entra com quantidade 1 e o preço atual do catálogo. Confira e ajuste na OS antes de aprovar.</p>
            <button class="btn-primario">Salvar modelo</button>
            @if ($editando)<a class="btn" href="{{ route('modelos-os.index') }}">Cancelar edição</a>@endif
        </form>
    </div>
</x-app>
