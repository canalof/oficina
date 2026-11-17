<x-app titulo="Mobilidade">
    <h1>Mobilidade</h1>
    <p class="mb-4 text-suave">Condutores, conferência de CNH, retirada de veículos, carros reserva e locações.</p>
    <nav class="mb-5 flex flex-wrap gap-2">
        <a class="btn" href="#condutores">Condutores</a>
        <a class="btn" href="{{ route('cnh.index', ['os' => $os?->id]) }}">Consultas de CNH</a>
        <a class="btn" href="#retiradas">Retiradas</a>
        <a class="btn" href="#frota">Frota</a>
        <a class="btn" href="#reservas">Reservas e locações</a>
        <a class="btn" href="{{ route('mobilidade.documentos') }}">Contratos e vistorias</a>
    </nav>
    @if ($os || $cliente)
        <p class="mb-4">Filtro: {{ $os ? 'OS '.$os->numeroFormatado() : $cliente->nome }} · <a href="{{ route('mobilidade.index') }}">Limpar filtro</a></p>
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
                <p class="text-suave">Abra uma OS em andamento e use o atalho Mobilidade para preparar a retirada.</p>
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

    <section id="frota" class="card card-corpo mb-4 space-y-3">
        <h2>Frota da oficina</h2>
        @forelse ($frota as $carro)
            <details class="border-t pt-3">
                <summary class="cursor-pointer">{{ $carro->placa }} · {{ $carro->descricao }} · {{ number_format($carro->km, 0, ',', '.') }} km</summary>
                @can('mobilidade.editar')
                    <form class="mt-3 space-y-3" method="post" action="{{ route('mobilidade.frota.update', $carro) }}">
                        @csrf @method('put')
                        @include('mobilidade._frota-campos')
                        <button class="btn-primario">Salvar veículo</button>
                    </form>
                @endcan
            </details>
        @empty
            <p class="text-suave">Nenhum carro da frota cadastrado.</p>
        @endforelse
        @can('mobilidade.editar')
            <details>
                <summary class="cursor-pointer font-semibold">Cadastrar carro da frota</summary>
                <form class="mt-3 space-y-3" method="post" action="{{ route('mobilidade.frota.store') }}">
                    @csrf
                    @include('mobilidade._frota-campos', ['carro' => null])
                    <button class="btn-primario">Cadastrar veículo</button>
                </form>
            </details>
        @endcan
    </section>

    <section id="reservas" class="card card-corpo mb-4 space-y-3">
        <h2>Reservas e locações</h2>
        @can('mobilidade.editar')
            <details>
                <summary class="cursor-pointer font-semibold">Nova reserva ou locação</summary>
                <form class="mt-3 space-y-3" method="post" action="{{ route('mobilidade.reserva.store') }}">
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="campo"><span>Ordem de serviço</span><select class="input" name="ordem_servico_id" required>
                            <option value="">Selecione</option>
                            @foreach ($ordens as $ordem)<option value="{{ $ordem->id }}" @selected($ordem->id === $os?->id)>{{ $ordem->numeroFormatado() }} · {{ $ordem->cliente?->nome }}</option>@endforeach
                        </select></label>
                        <label class="campo"><span>Condutor</span><select class="input" name="condutor_id" required>
                            <option value="">Selecione</option>
                            @foreach ($condutores as $condutor)<option value="{{ $condutor->id }}">{{ $condutor->nome }}</option>@endforeach
                        </select></label>
                        <label class="campo"><span>Modalidade</span><select class="input" name="tipo">
                            <option value="reserva">Carro reserva</option><option value="locacao">Locação própria</option><option value="parceira">Locadora parceira</option>
                        </select></label>
                        <label class="campo"><span>Veículo da frota (deixe em branco para parceira)</span><select class="input" name="frota_id">
                            <option value="">Selecione</option>
                            @foreach ($frota->where('situacao', 'disponivel') as $carro)<option value="{{ $carro->id }}">{{ $carro->placa }} · {{ $carro->descricao }}</option>@endforeach
                        </select></label>
                        <x-campo nome="parceira" rotulo="Nome da locadora parceira" maxlength="120" />
                        <x-campo nome="contrato_externo" rotulo="Referência do contrato externo" maxlength="120" />
                        <x-campo nome="inicio" rotulo="Início" tipo="datetime-local" required />
                        <x-campo nome="fim" rotulo="Fim previsto" tipo="datetime-local" required />
                        <x-campo nome="diaria_centavos" rotulo="Diária em centavos (R$ 50,00 = 5000)" tipo="number" min="0" />
                        <x-campo nome="caucao_centavos" rotulo="Caução em centavos" tipo="number" min="0" />
                    </div>
                    <button class="btn-primario">Registrar reserva</button>
                </form>
            </details>
        @endcan
        @forelse ($reservas as $reserva)
            <details class="border-t pt-3">
                <summary class="cursor-pointer">#{{ $reserva->id }} · {{ ['reserva'=>'Carro reserva', 'locacao'=>'Locação própria', 'parceira'=>'Locadora parceira'][$reserva->tipo] }} · {{ ucfirst($reserva->status) }} · {{ $reserva->inicio->format('d/m H:i') }} a {{ $reserva->fim->format('d/m H:i') }}</summary>
                <p class="my-3 text-suave">OS #{{ $reserva->ordem_servico_id }} · {{ $reserva->parceira ?: 'Veículo da frota #'.$reserva->frota_id }}
                    @if ($reserva->conta_receber_id) · Título a receber #{{ $reserva->conta_receber_id }} @endif
                </p>
                @foreach (['entrega', 'devolucao'] as $etapa)
                    @if ($reserva->$etapa)
                        <p>{{ ucfirst($etapa) }} · {{ $reserva->$etapa['km'] }} km · combustível {{ $reserva->$etapa['combustivel'] }}/4 · {{ $reserva->$etapa['vistoria'] }}</p>
                    @endif
                @endforeach
                @can('mobilidade.editar')
                    @if ($reserva->status === 'reservada')
                        @include('mobilidade._acao-reserva', ['acao' => 'entregar'])
                        <form class="mt-3" method="post" action="{{ route('mobilidade.reserva.acao', [$reserva, 'cancelar']) }}">
                            @csrf <button class="btn btn-sm">Cancelar reserva</button>
                        </form>
                    @elseif ($reserva->status === 'entregue')
                        @include('mobilidade._acao-reserva', ['acao' => 'devolver'])
                    @endif
                    @if (in_array($reserva->status, ['reservada', 'entregue']))
                        <form class="my-3 flex flex-wrap items-end gap-2" method="post" action="{{ route('mobilidade.reserva.acao', [$reserva, 'prorrogar']) }}">
                            @csrf
                            <x-campo nome="fim" rotulo="Novo fim" tipo="datetime-local" required />
                            <button class="btn btn-sm">Prorrogar</button>
                        </form>
                    @endif
                @endcan
                @include('mobilidade._anexos', ['registro' => $reserva, 'vinculo' => 'reserva_id', 'etapas' => array_filter(['entrega' => $reserva->entrega ? 'entrega' : null, 'devolucao' => $reserva->devolucao ? 'devolucao' : null]), 'fotos' => $fotosReserva->get($reserva->id, collect())])
                @include('mobilidade._gerar', ['registro' => $reserva, 'vinculo' => 'reserva_id', 'documentosDisponiveis' => array_filter([$reserva->tipo === 'reserva' ? 'carro_reserva' : 'locacao', $reserva->entrega ? 'vistoria' : null, $reserva->devolucao ? 'devolucao' : null])])
            </details>
        @empty
            <p class="text-suave">Nenhuma reserva registrada neste filtro.</p>
        @endforelse
        {{ $reservas->links() }}
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
