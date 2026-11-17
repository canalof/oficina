@php
    use App\Models\Agendamento;
    $f = $editando ?? $novo;
    $agendamentos = $porDia->flatten(1);
    $ativos = $agendamentos->whereIn('status', ['agendado', 'confirmado', 'chegou', 'proposto']);
    $proximo = $ativos->first(fn ($a) => $a->inicio->isFuture() && $a->status !== 'chegou');
@endphp
<x-app titulo="Agenda">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1>Agenda da oficina</h1>
            <p class="text-suave">Organize horários, confirme visitas e abra a OS na chegada.</p>
        </div>
        @can('agenda.editar')
            <a href="{{ $editando ? route('agenda.index', ['semana' => $inicio->toDateString()]) : '' }}#form-agenda" class="btn-sinal">Novo agendamento</a>
        @endcan
    </div>

    @if ($solicitacoes->isNotEmpty())
        <section class="card card-corpo mb-5 space-y-3">
            <h2>Solicitações do portal</h2>
            @foreach ($solicitacoes as $pedido)
                <article class="space-y-2 rounded border border-linha p-3">
                    <p class="font-medium">{{ $pedido->veiculo?->placaFormatada() ?? 'Veículo indisponível' }} · {{ $pedido->servico }}</p>
                    <p class="text-sm">Horário solicitado {{ $pedido->preferencia->format('d/m/Y H:i') }}</p>
                    @can('agenda.editar')
                        <form method="post" action="{{ route('agenda.solicitacoes.responder', $pedido) }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <label class="campo"><span>Horário a confirmar</span><input class="input" type="datetime-local" name="inicio" value="{{ $pedido->preferencia->format('Y-m-d\TH:i') }}" required></label>
                            <label class="campo"><span>Duração (min)</span><input class="input" type="number" name="duracao_minutos" min="15" max="600" value="60" required></label>
                            <button class="btn-primario" name="decisao" value="confirmar">Confirmar ou propor horário</button>
                        </form>
                        <form method="post" action="{{ route('agenda.solicitacoes.responder', $pedido) }}">
                            @csrf <button class="btn btn-sm" name="decisao" value="recusar">Recusar solicitação</button>
                        </form>
                    @endcan
                </article>
            @endforeach
            {{ $solicitacoes->links() }}
        </section>
    @endif

    <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" aria-label="Resumo da semana">
        <div class="card card-corpo"><p class="text-sm text-suave">Agendamentos da semana</p><p class="mt-1 text-2xl font-semibold">{{ $ativos->count() }}</p></div>
        <div class="card card-corpo"><p class="text-sm text-suave">Confirmados</p><p class="mt-1 text-2xl font-semibold">{{ $ativos->where('status', 'confirmado')->count() }}</p></div>
        <div class="card card-corpo"><p class="text-sm text-suave">Capacidade simultânea</p><p class="mt-1 text-2xl font-semibold">{{ auth()->user()->empresa->agenda_capacidade }} <span class="text-sm font-normal">carro(s)</span></p></div>
        <div class="card card-corpo">
            <p class="text-sm text-suave">Próximo horário nesta semana</p>
            @if ($proximo)
                <p class="mt-1 font-semibold">{{ $proximo->inicio->format('d/m \à\s H:i') }}</p>
                <p class="truncate text-sm text-suave">{{ $proximo->nome }} · {{ $proximo->servico }}</p>
            @else
                <p class="mt-1 font-semibold">Sem horários futuros</p>
            @endif
        </div>
    </div>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2>Calendário semanal</h2>
        <div class="flex items-center gap-2">
            <a href="{{ route('agenda.index', ['semana' => $inicio->copy()->subWeek()->toDateString()]) }}" class="btn btn-sm" aria-label="Semana anterior">‹</a>
            <span class="min-w-[170px] text-center font-semibold">{{ $inicio->format('d/m') }} a {{ $inicio->copy()->addDays(5)->format('d/m/Y') }}</span>
            <a href="{{ route('agenda.index', ['semana' => $inicio->copy()->addWeek()->toDateString()]) }}" class="btn btn-sm" aria-label="Próxima semana">›</a>
            <a href="{{ route('agenda.index') }}" class="btn btn-sm">Hoje</a>
        </div>
    </div>

    <div class="mb-6">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            @foreach ($dias as $dia)
                @php $lista = $porDia->get($dia->toDateString(), collect()); @endphp
                <section @class(['min-w-0 rounded-lg border p-2', 'border-sinal bg-[#fff7df]' => $dia->isToday(), 'border-linha bg-[#e8eaed]' => ! $dia->isToday()]) @if($dia->isToday()) data-hoje @endif>
                    <div class="mb-2 flex items-center justify-between gap-2 px-1">
                        <div>
                            <h3 class="text-[15px]">{{ ucfirst($dia->translatedFormat('D d/m')) }}</h3>
                            <p class="text-xs text-suave">{{ $lista->whereIn('status', ['agendado', 'confirmado', 'chegou'])->count() }} agendamento(s)</p>
                        </div>
                        @if ($dia->isToday())<span class="rotulo border-sinal">Hoje</span>@endif
                    </div>
                    <div class="space-y-2">
                        @forelse ($lista as $a)
                            <div class="rounded-md border border-l-4 border-linha bg-white p-2.5 text-sm {{ $a->statusCor() }}">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold">{{ $a->inicio->format('H:i') }}</span>
                                    @if ($a->placa)<x-placa :placa="$a->placa" />@endif
                                </div>
                                <div class="mt-1 truncate font-medium">{{ $a->nome }}</div>
                                <div class="truncate text-suave">{{ $a->servico }}</div>
                                <div class="mt-1 text-xs text-suave">{{ Agendamento::STATUS[$a->status] }}</div>
                                @can('agenda.editar')
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @if (in_array($a->status, ['agendado', 'confirmado']))
                                            @if ($a->veiculo_id && (! $a->cliente_id || $a->status === 'agendado') && auth()->user()->can('os.editar'))
                                                <a href="{{ route('os.create', ['veiculo_id' => $a->veiculo_id]) }}" class="rounded border border-linha px-1.5 py-0.5 text-xs font-semibold hover:border-asfalto-3">Chegou: abrir OS</a>
                                            @endif
                                            <form method="post" action="{{ route('agenda.update', $a) }}">@csrf @method('put')<input type="hidden" name="status" value="{{ $a->status === 'agendado' ? 'confirmado' : 'chegou' }}"><button class="rounded border border-linha px-1.5 py-0.5 text-xs font-semibold hover:border-asfalto-3">{{ $a->status === 'agendado' ? 'Confirmou' : 'Chegou' }}</button></form>
                                            <form method="post" action="{{ route('agenda.update', $a) }}">@csrf @method('put')<input type="hidden" name="status" value="faltou"><button class="rounded border border-linha px-1.5 py-0.5 text-xs hover:border-erro">Não veio</button></form>
                                        @endif
                                        @if ($w = $a->linkWhatsapp('Olá, '.explode(' ', trim($a->nome))[0].'! Confirmando seu horário '.($a->inicio->isToday() ? 'hoje' : ($a->inicio->isTomorrow() ? 'amanhã' : 'em '.$a->inicio->format('d/m'))).' às '.$a->inicio->format('H:i').' na '.auth()->user()->empresa->nome_fantasia.' ('.$a->servico.'). Podemos confirmar?'))
                                            <a href="{{ $w }}" target="_blank" rel="noopener" class="rounded border border-linha px-1.5 py-0.5 text-xs hover:border-asfalto-3">WhatsApp</a>
                                        @endif
                                        <a href="{{ route('agenda.index', ['semana' => $inicio->toDateString(), 'editar' => $a->id]) }}#form-agenda" class="rounded px-1.5 py-0.5 text-xs text-suave hover:text-texto">Editar</a>
                                    </div>
                                @endcan
                                @can('os.editar')
                                    @if ($a->ordem_servico_id)
                                        <a class="btn btn-sm mt-1" href="{{ route('os.show', $a->ordem_servico_id) }}">Abrir OS</a>
                                    @elseif ($a->veiculo_id && $a->cliente_id && in_array($a->status, ['confirmado', 'chegou'], true))
                                        @can('agenda.editar')
                                            <form method="post" class="mt-1" action="{{ route('agenda.abrir-os', $a) }}">
                                                @csrf <button class="btn btn-sm">Chegou: abrir OS</button>
                                            </form>
                                        @endcan
                                    @endif
                                @endcan
                            </div>
                        @empty
                            <p class="rounded-md border border-dashed border-linha bg-white px-2 py-3 text-center text-xs text-suave">Sem agendamentos</p>
                        @endforelse
                    </div>
                    @can('agenda.editar')
                        @if (! $dia->isPast() || $dia->isToday())
                            <a href="{{ route('agenda.index', ['semana' => $inicio->toDateString(), 'dia' => $dia->toDateString()]) }}#form-agenda" class="btn btn-sm mt-2 w-full" aria-label="Agendar para {{ $dia->format('d/m') }}">+ Agendar</a>
                        @endif
                    @endcan
                </section>
            @endforeach
        </div>
    </div>

    @can('agenda.editar')
        <section class="card card-corpo max-w-3xl" id="form-agenda">
            <h2 class="mb-4">{{ $editando ? 'Editar agendamento' : 'Novo agendamento' }}</h2>
            <form method="post" action="{{ $editando ? route('agenda.update', $editando) : route('agenda.store') }}" class="grid gap-3 sm:grid-cols-6">
                @csrf @if($editando) @method('put') @endif
                @if (request('lembrete'))<input type="hidden" name="lembrete_id" value="{{ request('lembrete') }}">@endif
                <label class="campo sm:col-span-3"><span>Cliente</span>
                    <select name="cliente_id" class="input">
                        <option value="">Cliente novo (escreva o nome)</option>
                        @foreach ($clientes as $c)<option value="{{ $c->id }}" @selected((int) old('cliente_id', $f->cliente_id) === $c->id)>{{ $c->nome }}</option>@endforeach
                    </select>
                </label>
                <x-campo nome="nome" rotulo="Nome (se for cliente novo)" :valor="$f->cliente_id ? '' : $f->nome" class="sm:col-span-3" />
                <x-campo nome="telefone" rotulo="WhatsApp" :valor="$f->telefone" mascara="telefone" class="sm:col-span-2" />
                <x-campo nome="placa" rotulo="Placa" :valor="$f->placa" mascara="placa" class="sm:col-span-2" />
                <x-campo nome="servico" rotulo="Serviço" :valor="$f->servico" required placeholder="Ex.: Revisão + alinhamento" class="sm:col-span-2" />
                <x-campo nome="inicio" rotulo="Dia e hora" tipo="datetime-local" :valor="$f->inicio?->format('Y-m-d\TH:i')" required class="sm:col-span-3" />
                <label class="campo sm:col-span-3"><span>Duração</span>
                    <select name="duracao_minutos" class="input">
                        @foreach ([30 => '30 min', 60 => '1 hora', 90 => '1h30', 120 => '2 horas', 180 => '3 horas', 240 => '4 horas', 480 => 'Dia todo'] as $m => $r)<option value="{{ $m }}" @selected((int) old('duracao_minutos', $f->duracao_minutos) === $m)>{{ $r }}</option>@endforeach
                    </select>
                </label>
                <label class="flex items-center gap-2 text-[15px] sm:col-span-6"><input type="checkbox" name="encaixar" value="1" class="h-4 w-4 accent-asfalto"> Encaixar mesmo assim (passa da capacidade de {{ auth()->user()->empresa->agenda_capacidade }} carros no mesmo horário)</label>
                <label class="campo sm:col-span-6"><span>Observações</span><textarea name="observacoes" rows="2" class="input">{{ old('observacoes', $f->observacoes) }}</textarea></label>
                <div class="flex flex-wrap gap-2 sm:col-span-6">
                    <button class="btn-primario">{{ $editando ? 'Salvar' : 'Agendar' }}</button>
                    @if ($editando)
                        <a href="{{ route('agenda.index', ['semana' => $inicio->toDateString()]) }}" class="btn">Cancelar edição</a>
                    @endif
                </div>
            </form>
            @if ($editando)
                <form method="post" action="{{ route('agenda.update', $editando) }}" class="mt-3">@csrf @method('put')<input type="hidden" name="status" value="cancelado"><button class="btn-perigo btn-sm">Cliente desmarcou</button></form>
            @endif
        </section>
    @endcan
</x-app>
