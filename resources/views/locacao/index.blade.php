@php
    use App\Models\MobilidadeReserva;
    use App\Support\Dinheiro;
    use App\Support\Placa;
    $situacoes = ['reservada' => 'Reservada', 'entregue' => 'Com o cliente', 'devolvida' => 'Devolvida', 'cancelada' => 'Cancelada'];
    $disponiveis = $frota->where('situacao', 'disponivel');
@endphp
<x-app titulo="Locação">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1>Locação</h1>
        <div class="flex flex-wrap gap-2">
            <a class="btn" href="{{ route('mobilidade.documentos') }}">Contratos e vistorias</a>
            @can('mobilidade.ver')<a class="btn" href="{{ route('mobilidade.index') }}#condutores">Condutores</a>@endcan
        </div>
    </div>

    @if (session('erro'))<p class="erro-msg mb-3" role="alert">{{ session('erro') }}</p>@endif
    @if ($errors->any())<p class="erro-msg mb-3" role="alert">{{ $errors->first() }}</p>@endif

    <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach ([['Carros disponíveis', $resumo['disponiveis']], ['Com cliente agora', $resumo['emUso']], ['Saem hoje', $resumo['saemHoje']], ['Devolução hoje ou atrasada', $resumo['voltamHoje']]] as [$rotulo, $valor])
            <div class="card card-corpo"><div class="text-sm text-suave">{{ $rotulo }}</div><div class="text-2xl font-bold">{{ $valor }}</div></div>
        @endforeach
    </div>

    @can('locacao.editar')
        <div class="mb-5 grid gap-3 lg:grid-cols-2">
            <details class="card card-corpo" @if (old('modalidade') === 'carro_reserva' || $os) open @endif>
                <summary class="cursor-pointer font-semibold">Novo carro reserva <span class="font-normal text-suave">· cliente com carro na oficina</span></summary>
                <form class="mt-3 space-y-3" method="post" action="{{ route('locacao.reserva.store') }}">
                    @csrf
                    <input type="hidden" name="modalidade" value="carro_reserva">
                    <label class="campo"><span>Ordem de serviço</span><select class="input" name="ordem_servico_id" required>
                        <option value="">Selecione a OS</option>
                        @foreach ($ordens as $ordem)
                            <option value="{{ $ordem->id }}" @selected((int) old('ordem_servico_id', $os?->id) === $ordem->id)>{{ $ordem->numeroFormatado() }} · {{ Placa::formatar($ordem->veiculo?->placa) }} · {{ $ordem->cliente?->nome }}</option>
                        @endforeach
                    </select></label>
                    @include('locacao._campos-reserva', ['permiteParceira' => true])
                    <button class="btn-primario">Registrar carro reserva</button>
                </form>
            </details>
            <details class="card card-corpo" @if (old('modalidade') === 'aluguel' || ($cliente && ! $os)) open @endif>
                <summary class="cursor-pointer font-semibold">Novo aluguel <span class="font-normal text-suave">· sem ligação com a oficina</span></summary>
                <form class="mt-3 space-y-3" method="post" action="{{ route('locacao.reserva.store') }}">
                    @csrf
                    <input type="hidden" name="modalidade" value="aluguel">
                    <input type="hidden" name="tipo" value="frota">
                    <label class="campo"><span>Cliente</span><select class="input" name="cliente_id" required>
                        <option value="">Selecione o cliente</option>
                        @foreach ($clientes as $c)<option value="{{ $c->id }}" @selected((int) old('cliente_id', $cliente?->id) === $c->id)>{{ $c->nome }}</option>@endforeach
                    </select></label>
                    @can('clientes.editar')<p class="text-sm text-suave">Cliente novo? <a class="underline" href="{{ route('clientes.create') }}">Cadastre aqui</a> e volte.</p>@endcan
                    @include('locacao._campos-reserva', ['permiteParceira' => false])
                    <button class="btn-primario">Registrar aluguel</button>
                </form>
            </details>
        </div>
    @endcan

    <section class="card mb-5" id="reservas">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-linha px-5 py-3">
            <h2>Reservas e aluguéis</h2>
            <nav class="flex flex-wrap gap-1" aria-label="Filtro">
                @foreach ([null => 'Todas', 'carro_reserva' => 'Carro reserva', 'aluguel' => 'Aluguel'] as $chave => $rotulo)
                    <a href="{{ route('locacao.index', array_filter(['modalidade' => $chave, 'situacao' => $situacao, 'os' => $os?->id, 'cliente' => $cliente?->id])) }}"
                       @class(['btn btn-sm', 'border-sinal' => $modalidade === ($chave ?: null)]) @if($modalidade === ($chave ?: null)) aria-current="page" @endif>{{ $rotulo }}</a>
                @endforeach
                <span class="mx-1 border-l border-linha"></span>
                @foreach (['abertas' => 'Em aberto', 'encerradas' => 'Encerradas'] as $chave => $rotulo)
                    <a href="{{ route('locacao.index', array_filter(['modalidade' => $modalidade, 'situacao' => $chave, 'os' => $os?->id, 'cliente' => $cliente?->id])) }}"
                       @class(['btn btn-sm', 'border-sinal' => $situacao === $chave]) @if($situacao === $chave) aria-current="page" @endif>{{ $rotulo }}</a>
                @endforeach
            </nav>
        </div>
        @if ($os || $cliente)
            <p class="px-5 pt-3 text-sm">Mostrando só {{ $os ? 'a OS '.$os->numeroFormatado() : 'o cliente '.$cliente->nome }} · <a class="underline" href="{{ route('locacao.index') }}">ver todas</a></p>
        @endif
        <div class="divide-y divide-linha">
            @forelse ($reservas as $reserva)
                <details class="px-5 py-3" id="reserva-{{ $reserva->id }}">
                    <summary class="flex cursor-pointer flex-wrap items-center gap-x-3 gap-y-1">
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $reserva->ehAluguel() ? 'bg-[#e6eef8] text-[#1f4f82]' : 'bg-[#eef3e6] text-[#3f5f1f]' }}">{{ MobilidadeReserva::MODALIDADES[$reserva->modalidade] }}</span>
                        <strong>{{ $reserva->cliente?->nome ?? 'Cliente não informado' }}</strong>
                        <span class="text-suave">{{ $reserva->frota ? Placa::formatar($reserva->frota->placa).' · '.$reserva->frota->descricao : 'Parceira: '.$reserva->parceira }}</span>
                        <span class="text-suave">{{ $reserva->inicio->format('d/m H:i') }} a {{ $reserva->fim->format('d/m H:i') }}</span>
                        <span class="ml-auto text-sm font-semibold">{{ $situacoes[$reserva->status] ?? $reserva->status }}</span>
                    </summary>
                    <div class="mt-3 space-y-2 text-[15px]">
                        <p>
                            Condutor: {{ $reserva->condutor?->nome }}
                            @if ($reserva->ordemServico) · <a class="underline" href="{{ route('os.show', $reserva->ordemServico) }}">OS {{ $reserva->ordemServico->numeroFormatado() }}</a>@endif
                            · Diária {{ Dinheiro::formatar($reserva->diaria_centavos) }}
                            @if ($reserva->caucao_centavos) · Caução {{ Dinheiro::formatar($reserva->caucao_centavos) }}@endif
                            @if ($reserva->contrato_externo) · Contrato {{ $reserva->contrato_externo }}@endif
                            @if ($reserva->conta_receber_id) · <a class="underline" href="{{ route('receber.index') }}">Título a receber #{{ $reserva->conta_receber_id }}</a>@endif
                        </p>
                        @foreach (['entrega' => 'Saída', 'devolucao' => 'Devolução'] as $etapa => $rotulo)
                            @if ($reserva->$etapa)
                                <p>{{ $rotulo }}: {{ number_format($reserva->$etapa['km'], 0, ',', '.') }} km · combustível {{ $reserva->$etapa['combustivel'] }}/4 · {{ $reserva->$etapa['vistoria'] }}</p>
                            @endif
                        @endforeach
                        @if ($reserva->ocorrencias)<p>Ocorrências: {{ $reserva->ocorrencias }}</p>@endif
                        @can('locacao.editar')
                            @if ($reserva->status === 'reservada')
                                @include('locacao._acao-reserva', ['acao' => 'entregar'])
                                <form class="mt-3" method="post" action="{{ route('locacao.reserva.acao', [$reserva, 'cancelar']) }}">
                                    @csrf <button class="btn btn-sm">Cancelar reserva</button>
                                </form>
                            @elseif ($reserva->status === 'entregue')
                                @include('locacao._acao-reserva', ['acao' => 'devolver'])
                            @endif
                            @if (in_array($reserva->status, ['reservada', 'entregue']))
                                <form class="my-3 flex flex-wrap items-end gap-2" method="post" action="{{ route('locacao.reserva.acao', [$reserva, 'prorrogar']) }}">
                                    @csrf
                                    <x-campo nome="fim" rotulo="Novo fim" tipo="datetime-local" required />
                                    <button class="btn btn-sm">Prorrogar</button>
                                </form>
                            @endif
                        @endcan
                        @include('mobilidade._anexos', ['registro' => $reserva, 'vinculo' => 'reserva_id', 'permissao' => 'locacao.editar', 'etapas' => array_filter(['entrega' => $reserva->entrega ? 'entrega' : null, 'devolucao' => $reserva->devolucao ? 'devolucao' : null]), 'fotos' => $fotosReserva->get($reserva->id, collect())])
                        @include('mobilidade._gerar', ['registro' => $reserva, 'vinculo' => 'reserva_id', 'permissao' => 'locacao.editar', 'documentosDisponiveis' => array_values(array_filter([$reserva->ehAluguel() || $reserva->tipo === 'parceira' ? 'locacao' : 'carro_reserva', $reserva->entrega ? 'vistoria' : null, $reserva->devolucao ? 'devolucao' : null]))])
                    </div>
                </details>
            @empty
                <p class="px-5 py-8 text-center text-suave">Nenhuma reserva neste filtro.</p>
            @endforelse
        </div>
        <div class="px-5 py-3">{{ $reservas->links() }}</div>
    </section>

    <section class="card card-corpo space-y-3" id="frota">
        <h2>Frota</h2>
        @forelse ($frota as $carro)
            <details class="border-t border-linha pt-3 first-of-type:border-t-0">
                <summary class="cursor-pointer">{{ Placa::formatar($carro->placa) }} · {{ $carro->descricao }} · {{ number_format($carro->km, 0, ',', '.') }} km · {{ ['disponivel' => 'Disponível', 'manutencao' => 'Em manutenção', 'inativo' => 'Inativo'][$carro->situacao] }}</summary>
                @can('locacao.editar')
                    <form class="mt-3 space-y-3" method="post" action="{{ route('locacao.frota.update', $carro) }}">
                        @csrf @method('put')
                        @include('mobilidade._frota-campos')
                        <button class="btn-primario">Salvar carro</button>
                    </form>
                @endcan
            </details>
        @empty
            <p class="text-suave">Nenhum carro cadastrado. Cadastre a frota para registrar carro reserva e aluguel.</p>
        @endforelse
        @can('locacao.editar')
            <details>
                <summary class="cursor-pointer font-semibold">Cadastrar carro</summary>
                <form class="mt-3 space-y-3" method="post" action="{{ route('locacao.frota.store') }}">
                    @csrf
                    @include('mobilidade._frota-campos', ['carro' => null])
                    <button class="btn-primario">Cadastrar carro</button>
                </form>
            </details>
        @endcan
    </section>
</x-app>
