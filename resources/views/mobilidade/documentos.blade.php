<x-app titulo="Contratos e vistorias">
    <h1>Contratos e vistorias</h1>
    <div class="my-4 flex flex-wrap gap-2">
        @can('mobilidade.ver')<a class="btn" href="{{ route('mobilidade.index') }}">Voltar para Condutores e retirada</a>@endcan
        @can('locacao.ver')<a class="btn" href="{{ route('locacao.index') }}">Voltar para Locação</a>@endcan
    </div>
    <p class="mb-4 text-suave">Gere os documentos dentro de cada retirada ou reserva. Cada documento guarda o texto do momento em que foi gerado.</p>
    @if ($errors->any())<p class="erro-msg mb-4">{{ $errors->first() }}</p>@endif
    @if (session('ok'))<p class="mb-4" role="status">{{ session('ok') }}</p>@endif
    @if (auth()->user()->can('mobilidade.excecao') || auth()->user()->can('locacao.excecao'))
        <section class="card card-corpo mb-4 space-y-3">
            <h2>Modelos da oficina</h2>
            @foreach ($tipos as $tipo => $rotulo)
                @continue(! auth()->user()->can(in_array($tipo, \App\Http\Controllers\MobilidadeDocumentoController::DA_RETIRADA, true) ? 'mobilidade.excecao' : 'locacao.excecao'))
                <details>
                    <summary class="cursor-pointer">{{ $rotulo }}</summary>
                    <form class="my-3 space-y-3" method="post" action="{{ route('mobilidade.modelo', $tipo) }}">
                        @csrf @method('put')
                        <label class="campo"><span>Cláusulas do modelo</span><textarea class="input" name="texto" maxlength="12000" rows="6" required>{{ $modelos->get($tipo)?->texto }}</textarea></label>
                        <button class="btn-primario">Salvar modelo</button>
                    </form>
                </details>
            @endforeach
        </section>
    @endif
    <section class="card card-corpo space-y-3">
        <h2>Documentos gerados</h2>
        @forelse ($documentos as $documento)
            <p><a class="btn btn-sm" href="{{ route('mobilidade.documento', $documento) }}">{{ $tipos[$documento->tipo] }} #{{ $documento->id }}</a> {{ $documento->created_at->format('d/m/Y H:i') }}</p>
        @empty
            <p>Nenhum documento gerado.</p>
        @endforelse
        {{ $documentos->links() }}
    </section>
</x-app>
