@php use App\Support\StatusOs; use App\Support\Dinheiro; @endphp
<x-app titulo="Ordens de serviço">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1>Ordens de serviço</h1>
        @can('os.editar')<a href="{{ route('recepcao') }}" class="btn-primario">Abrir OS pela placa</a>@endcan
    </div>

    <nav class="mb-5 flex gap-1 border-b border-linha" aria-label="Visão">
        @foreach (['patio' => 'No pátio', 'todas' => 'Todas as OS'] as $chave => $rotulo)
            <a href="{{ route('os.index', ['visao' => $chave]) }}" @class([
                '-mb-px border-b-[3px] px-4 py-2.5 text-[15px] font-semibold',
                'border-sinal text-texto' => $visao === $chave,
                'border-transparent text-suave hover:text-texto' => $visao !== $chave,
            ]) @if($visao === $chave) aria-current="page" @endif>{{ $rotulo }}</a>
        @endforeach
    </nav>

    @if ($visao === 'patio')
        @if ($colunas->flatten()->isEmpty())
            <div class="card px-5 py-14 text-center">
                <p class="text-lg font-semibold">Nenhum carro no pátio agora.</p>
                <p class="mt-1 text-suave">Quando um carro chegar, digite a placa na Recepção e abra a OS.</p>
            </div>
        @else
            <div class="-mx-4 overflow-x-auto px-4 pb-2 lg:mx-0 lg:px-0">
                <div class="grid min-w-[900px] grid-cols-5 gap-3">
                    @foreach (StatusOs::doPatio() as $st)
                        @php $lista = $colunas->get($st->value, collect()); @endphp
                        <section class="flex flex-col rounded-lg bg-[#e8eaed] p-2" aria-label="{{ $st->rotulo() }}">
                            <h2 class="flex items-center justify-between px-2 py-1.5 text-[15px]">
                                {{ $st->rotulo() }}
                                <span class="rounded-full bg-white px-2 text-sm font-semibold text-suave">{{ $lista->count() }}</span>
                            </h2>
                            <div class="mt-1 space-y-2">
                                @foreach ($lista as $os)
                                    <a href="{{ route('os.show', $os) }}" class="block rounded-md border border-linha bg-white p-3 hover:border-asfalto-3">
                                        <div class="flex items-center justify-between gap-2">
                                            <x-placa :placa="$os->veiculo->placa" />
                                            <span class="text-sm text-suave">#{{ $os->numero }}</span>
                                        </div>
                                        <div class="mt-2 truncate font-semibold">{{ $os->veiculo->descricao() ?: 'Modelo não informado' }}</div>
                                        <div class="truncate text-sm text-suave">{{ $os->cliente?->nome ?? 'Sem cliente' }}</div>
                                        <div class="mt-2 flex items-center justify-between text-sm">
                                            <span class="font-semibold">{{ Dinheiro::formatar($os->total_centavos) }}</span>
                                            @if ($os->previsao_entrega)
                                                <span @class(['text-erro font-semibold' => $os->previsao_entrega->isPast() && $st !== StatusOs::Pronta, 'text-suave' => ! $os->previsao_entrega->isPast() || $st === StatusOs::Pronta])>
                                                    {{ $os->previsao_entrega->isToday() ? 'hoje '.$os->previsao_entrega->format('H:i') : $os->previsao_entrega->format('d/m H:i') }}
                                                </span>
                                            @endif
                                        </div>
                                        @if ($os->mecanico)<div class="mt-1 truncate text-xs text-suave">{{ $os->mecanico->name }}</div>@endif
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
            </div>
        @endif
    @else
        <form class="mb-4 flex flex-wrap gap-2" role="search">
            <input type="hidden" name="visao" value="todas">
            <label class="sr-only" for="busca">Buscar OS</label>
            <input id="busca" name="busca" value="{{ $busca }}" class="input max-w-xs" placeholder="Número, placa ou cliente">
            <label class="sr-only" for="status">Situação</label>
            <select id="status" name="status" class="input w-auto">
                <option value="">Todas as situações</option>
                @foreach (StatusOs::cases() as $s)
                    <option value="{{ $s->value }}" @selected($status === $s)>{{ $s->rotulo() }}</option>
                @endforeach
            </select>
            <button class="btn">Buscar</button>
            @if ($busca || $status)<a href="{{ route('os.index', ['visao' => 'todas']) }}" class="btn">Limpar</a>@endif
        </form>

        <div class="card overflow-x-auto">
            <table class="tabela">
                <thead><tr><th>OS</th><th>Placa</th><th class="hidden md:table-cell">Veículo</th><th>Cliente</th><th>Situação</th><th class="hidden sm:table-cell">Aberta em</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                @forelse ($ordens as $os)
                    <tr>
                        <td><a class="font-semibold hover:underline" href="{{ route('os.show', $os) }}">{{ $os->numeroFormatado() }}</a></td>
                        <td><x-placa :placa="$os->veiculo->placa" /></td>
                        <td class="hidden md:table-cell">{{ $os->veiculo->descricao() }}</td>
                        <td>{{ $os->cliente?->nome ?? '—' }}</td>
                        <td><span class="rotulo {{ $os->status->cor() }}">{{ $os->status->rotulo() }}</span></td>
                        <td class="hidden whitespace-nowrap sm:table-cell">{{ $os->created_at->format('d/m/Y') }}</td>
                        <td class="whitespace-nowrap text-right font-semibold">{{ Dinheiro::formatar($os->total_centavos) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-10 text-center text-suave">Nenhuma OS encontrada.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $ordens->links() }}
        </div>
    @endif
</x-app>
