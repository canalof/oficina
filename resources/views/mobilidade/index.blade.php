<x-app titulo="Condutores e retirada">
    <h1>Condutores e retirada</h1>
    <p class="mb-4 text-suave">Quem vai buscar o carro na oficina: cadastro do condutor, conferência da CNH e autorização de retirada.</p>
    <nav class="mb-5 flex flex-wrap gap-2">
        <a class="btn" href="#condutores">Condutores</a>
        <a class="btn" href="{{ route('cnh.index', ['os' => $os?->id]) }}">Consultas de CNH</a>
        <a class="btn" href="#retiradas">Retiradas</a>
        <a class="btn" href="{{ route('mobilidade.documentos') }}">Documentos de retirada</a>
        @can('locacao.ver')<a class="btn" href="{{ route('locacao.index', array_filter(['os' => $os?->id, 'cliente' => $cliente?->id])) }}">Ir para Locação</a>@endcan
    </nav>
    @if ($os || $cliente)
        <p class="mb-4">Filtro: {{ $os ? 'OS '.$os->numeroFormatado() : $cliente->nome }} · <a class="underline" href="{{ route('mobilidade.index') }}">Limpar filtro</a></p>
    @endif
    @foreach (['ok', 'erro'] as $mensagem)
        @if (session($mensagem))<p class="mb-3" role="status">{{ session($mensagem) }}</p>@endif
    @endforeach
    @if ($errors->any())<p class="erro-msg mb-3" role="alert">{{ $errors->first() }}</p>@endif

    <section id="condutores" class="card card-corpo mb-4 space-y-3">
        <h2>Condutores</h2>
        <p class="text-suave">Abra o cadastro para editar os dados ou conferir uma CNH com a câmera.</p>
        <ul class="space-y-2">
            @forelse ($condutores as $condutor)
                <li><a class="btn btn-sm" href="{{ route('mobilidade.condutor.show', ['condutor' => $condutor, 'os' => $os?->id]) }}">{{ $condutor->nome }}</a>
                <span class="text-suave">{{ $condutor->categoria ?? 'Sem categoria' }} · {{ $condutor->validade?->format('d/m/Y') ?? 'Sem validade informada' }}</span></li>
            @empty
                <li>Nenhum condutor cadastrado.</li>
            @endforelse
        </ul>
        @can('mobilidade.editar')
            <details>
                <summary class="cursor-pointer font-semibold">Cadastrar condutor</summary>
                <form class="mt-3 space-y-3" method="post" action="{{ route('mobilidade.condutor.store', ['os' => $os?->id]) }}">
                    @csrf
                    @include('mobilidade._condutor-campos', ['condutor' => null])
                    <button class="btn-primario">Salvar condutor</button>
                </form>
            </details>
        @endcan
    </section>

    <section id="retiradas" class="card card-corpo mb-4 space-y-3">
        <h2>Retirada de veículo da oficina</h2>
        @can('mobilidade.editar')
            @if ($os?->status->noPatio())
                <form class="space-y-3" method="post" action="{{ route('mobilidade.retirada.store', $os) }}">
                    @csrf
                    <label class="campo"><span>Condutor</span><select class="input" name="condutor_id" required>
                        <option value="">Selecione</option>
                        @foreach ($condutores as $condutor)<option value="{{ $condutor->id }}">{{ $condutor->nome }}</option>@endforeach
                    </select></label>
                    <label><input type="checkbox" name="terceiro" value="1"> Retirada por terceiro</label>
                    <label class="campo"><span>Autorização do responsável pela OS (obrigatória para terceiro)</span><textarea class="input" name="autorizacao" maxlength="2000"></textarea></label>
                    <x-campo nome="aceite_nome" rotulo="Nome de quem deu o aceite presencial" required maxlength="120" />
                    <label class="campo"><span>Checklist e observações da entrega</span><textarea class="input" name="checklist" maxlength="4000"></textarea></label>
                    @can('mobilidade.excecao')
                        <label class="campo"><span>Justificativa de exceção, se houver</span><textarea class="input" name="justificativa" minlength="15" maxlength="1000"></textarea></label>
                    @endcan
                    <button class="btn-primario">Preparar retirada</button>
                    <a class="btn" href="{{ route('os.show', $os) }}">Abrir OS para concluir entrega</a>
                </form>
            @else
                <p class="text-suave">Abra uma OS em andamento e use o botão Retirada para preparar a entrega a quem vai buscar o carro.</p>
            @endif
        @endcan
        @forelse ($retiradas as $retirada)
            <details class="border-t pt-3">
                <summary class="cursor-pointer">Retirada #{{ $retirada->id }} · OS #{{ $retirada->ordem_servico_id }} · {{ $retirada->entregue_em ? 'Entregue em '.$retirada->entregue_em->format('d/m/Y H:i') : 'Pendente' }}</summary>
                @include('mobilidade._anexos', ['registro' => $retirada, 'vinculo' => 'retirada_id', 'etapas' => ['retirada'], 'fotos' => $fotosRetirada->get($retirada->id, collect())])
                @include('mobilidade._gerar', ['registro' => $retirada, 'vinculo' => 'retirada_id', 'documentosDisponiveis' => $retirada->entregue_em ? ['autorizacao', 'entrega'] : ['autorizacao']])
            </details>
        @empty
            <p class="text-suave">Nenhuma retirada registrada neste filtro.</p>
        @endforelse
    </section>

    @can('mobilidade.excecao')
        <form class="card card-corpo space-y-3" method="post" action="{{ route('mobilidade.politica') }}">
            @csrf @method('put')
            <h2>Regras da oficina para a retirada pela OS</h2>
            <label class="block"><input type="checkbox" name="exige_cnh" value="1" @checked(auth()->user()->empresa->mobilidade_exige_cnh)> Exigir conferência assistida da CNH</label>
            <label class="block"><input type="checkbox" name="bloqueia_divergencia" value="1" @checked(auth()->user()->empresa->mobilidade_bloqueia_divergencia)> Bloquear divergências</label>
            <label class="block"><input type="checkbox" name="exige_oficial" value="1" @checked(auth()->user()->empresa->mobilidade_exige_oficial)> Exigir situação oficial (bloqueia a entrega enquanto a integração oficial estiver indisponível)</label>
            <button class="btn-primario">Salvar regras</button>
        </form>
    @endcan
</x-app>
