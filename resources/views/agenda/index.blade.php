@php use App\Models\Agendamento; $f = $editando ?? $novo; @endphp
<x-app titulo="Agenda">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1>Agenda</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('agenda.index', ['semana' => $inicio->copy()->subWeek()->toDateString()]) }}" class="btn btn-sm" aria-label="Semana anterior">‹</a>
            <span class="min-w-[170px] text-center font-semibold">{{ $inicio->format('d/m') }} a {{ $inicio->copy()->addDays(5)->format('d/m/Y') }}</span>
            <a href="{{ route('agenda.index', ['semana' => $inicio->copy()->addWeek()->toDateString()]) }}" class="btn btn-sm" aria-label="Próxima semana">›</a>
            <a href="{{ route('agenda.index') }}" class="btn btn-sm">Hoje</a>
        </div>
    </div>

    <div class="-mx-4 mb-6 overflow-x-auto px-4 lg:mx-0 lg:px-0">
        <div class="grid min-w-[880px] grid-cols-6 gap-2">
            @foreach ($dias as $dia)
                @php $lista = $porDia->get($dia->toDateString(), collect()); @endphp
                <section @class(['rounded-lg p-2', 'bg-[#fff7df] ring-2 ring-sinal' => $dia->isToday(), 'bg-[#e8eaed]' => ! $dia->isToday()]) @if($dia->isToday()) data-hoje @endif>
                    <h2 class="px-1 pb-2 text-[15px]">{{ ucfirst($dia->translatedFormat('D d/m')) }} <span class="text-sm font-normal text-suave">· {{ $lista->whereNotIn('status', ['cancelado'])->count() }}</span></h2>
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
                                            @if ($a->veiculo_id && auth()->user()->can('os.editar'))
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
                            </div>
                        @empty
                            <p class="px-1 py-3 text-center text-xs text-suave">Livre</p>
                        @endforelse
                    </div>
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
