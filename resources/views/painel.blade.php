<x-app titulo="Painel">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            @php $h = now()->hour; @endphp
            <h1>{{ $h < 12 ? 'Bom dia' : ($h < 18 ? 'Boa tarde' : 'Boa noite') }}, {{ explode(' ', auth()->user()->name)[0] }}</h1>
            <p class="text-suave">{{ now()->translatedFormat('l, d \d\e F') }}</p>
        </div>
        @can('veiculos.ver')
            <a href="{{ route('recepcao') }}" class="btn-sinal px-5 py-3 text-base">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="7" width="18" height="10" rx="2"/><path d="M3 10h18"/></svg>
                Receber um carro
            </a>
        @endcan
    </div>

    @php $avisos = collect([
        auth()->user()->can('caixa.operar') && ! $caixa ? ['O caixa está fechado', route('caixa.index'), 'Abrir caixa'] : null,
        auth()->user()->can('estoque.ver') && $estoqueBaixo ? [$estoqueBaixo.' '.($estoqueBaixo === 1 ? 'produto' : 'produtos').' no estoque mínimo', route('produtos.index', ['filtro' => 'minimo']), 'Ver'] : null,
        auth()->user()->can('financeiro.ver') && $receberVencido ? ['R$ '.number_format($receberVencido / 100, 2, ',', '.').' a receber vencido', route('receber.index', ['filtro' => 'vencidas']), 'Cobrar'] : null,
        auth()->user()->can('financeiro.ver') && $pagarHoje ? ['R$ '.number_format($pagarHoje / 100, 2, ',', '.').' em contas vencendo até hoje', route('pagar.index', ['filtro' => 'abertas']), 'Pagar'] : null,
        auth()->user()->can('agenda.ver') && $lembretesSemana ? [$lembretesSemana.' '.($lembretesSemana === 1 ? 'lembrete' : 'lembretes').' de revisão para enviar', route('lembretes.index'), 'Enviar'] : null,
    ])->filter(); @endphp
    @if ($avisos->isNotEmpty())
        <div class="mb-4 grid gap-2 sm:grid-cols-2">
            @foreach ($avisos as [$texto, $link, $acao])
                <a href="{{ $link }}" class="flex items-center justify-between gap-3 rounded-md border border-[#f0d58a] bg-[#fff7df] px-4 py-2.5 text-[15px] hover:border-sinal"><span>{{ $texto }}</span><span class="font-semibold">{{ $acao }}</span></a>
            @endforeach
        </div>
    @endif

    @can('agenda.ver')
        @if ($agendaHoje->isNotEmpty())
            <div class="card mb-4">
                <div class="flex items-center justify-between border-b border-linha px-5 py-3"><h2>Agenda de hoje</h2><a href="{{ route('agenda.index') }}" class="text-sm font-semibold text-asfalto-3">Ver agenda</a></div>
                <ul class="divide-y divide-linha">
                    @foreach ($agendaHoje as $a)
                        <li class="flex items-center gap-4 px-5 py-2.5">
                            <span class="w-12 font-semibold">{{ $a->inicio->format('H:i') }}</span>
                            @if ($a->placa)<x-placa :placa="$a->placa" />@endif
                            <span class="min-w-0 flex-1 truncate">{{ $a->nome }} · {{ $a->servico }}</span>
                            <span class="text-sm text-suave">{{ \App\Models\Agendamento::STATUS[$a->status] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endcan

    @can('os.ver')
        @php $patio = \App\Support\StatusOs::doPatio(); @endphp
        <a href="{{ route('os.index') }}" class="card mb-4 block hover:border-asfalto-3">
            <div class="flex items-center justify-between border-b border-linha px-5 py-3">
                <h2>No pátio agora</h2>
                <span class="text-sm font-semibold text-asfalto-3">Ver quadro</span>
            </div>
            <div class="grid grid-cols-5 divide-x divide-linha">
                @foreach ($patio as $st)
                    <div class="px-3 py-3 sm:px-5">
                        <div @class(['text-3xl font-semibold', 'text-sinal-escuro' => $st === \App\Support\StatusOs::Aguardando && ($porStatus[$st->value] ?? 0) > 0, 'text-ok' => $st === \App\Support\StatusOs::Pronta && ($porStatus[$st->value] ?? 0) > 0])>{{ $porStatus[$st->value] ?? 0 }}</div>
                        <div class="truncate text-xs text-suave sm:text-sm">{{ $st === \App\Support\StatusOs::Pronta ? 'Prontos' : $st->rotulo() }}</div>
                    </div>
                @endforeach
            </div>
        </a>

        @if ($atrasadas->isNotEmpty())
            <div class="card mb-4 border-l-4 border-l-erro">
                <div class="border-b border-linha px-5 py-3"><h2>Passou da previsão de entrega</h2></div>
                <ul class="divide-y divide-linha">
                    @foreach ($atrasadas as $o)
                        <li><a href="{{ route('os.show', $o) }}" class="flex items-center gap-4 px-5 py-2.5 hover:bg-[#fafbfc]">
                            <x-placa :placa="$o->veiculo->placa" />
                            <span class="min-w-0 flex-1 truncate">{{ $o->cliente?->nome ?? $o->veiculo->descricao() }} · {{ $o->status->rotulo() }}</span>
                            <span class="whitespace-nowrap text-sm font-semibold text-erro">{{ $o->previsao_entrega->format('d/m H:i') }}</span>
                        </a></li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endcan

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @can('os.ver')
            <div class="card card-corpo"><div class="text-sm text-suave">Carros entregues no mês</div><div class="mt-1 text-3xl font-semibold">{{ $entreguesMes }}</div><div class="text-sm text-suave">R$ {{ number_format($valorEntreguesMes / 100, 2, ',', '.') }} em serviços</div></div>
        @endcan
        <div class="card card-corpo"><div class="text-sm text-suave">Clientes</div><div class="mt-1 text-3xl font-semibold">{{ number_format($totalClientes, 0, ',', '.') }}</div><div class="text-sm text-suave">+{{ $clientesMes }} neste mês</div></div>
        <div class="card card-corpo"><div class="text-sm text-suave">Veículos</div><div class="mt-1 text-3xl font-semibold">{{ number_format($totalVeiculos, 0, ',', '.') }}</div><div class="text-sm text-suave">+{{ $veiculosMes }} neste mês</div></div>
        <div class="card card-corpo"><div class="text-sm text-suave">Consulta de placa no mês</div><div class="mt-1 text-3xl font-semibold">{{ $consultasMes }}</div><div class="text-sm text-suave">R$ {{ number_format($gastoPlacaMes / 100, 2, ',', '.') }} gastos</div></div>
    </div>

    <div class="card mt-6">
        <div class="flex items-center justify-between border-b border-linha px-5 py-4">
            <h2>Últimos veículos cadastrados</h2>
            <a href="{{ route('veiculos.index') }}" class="text-sm font-semibold text-asfalto-3 hover:underline">Ver todos</a>
        </div>
        @if ($ultimosVeiculos->isEmpty())
            <p class="px-5 py-10 text-center text-suave">Nenhum veículo ainda. Use <a class="font-semibold underline" href="{{ route('recepcao') }}">Receber um carro</a> para cadastrar o primeiro pela placa.</p>
        @else
            <ul class="divide-y divide-linha">
                @foreach ($ultimosVeiculos as $v)
                    <li><a href="{{ route('veiculos.show', $v) }}" class="flex items-center gap-4 px-5 py-3 hover:bg-[#fafbfc]">
                        <x-placa :placa="$v->placa" />
                        <span class="min-w-0 flex-1"><span class="block truncate font-medium">{{ $v->descricao() ?: 'Sem modelo' }}</span><span class="block truncate text-sm text-suave">{{ $v->cliente?->nome ?? 'Sem dono cadastrado' }}</span></span>
                        <span class="hidden text-sm text-suave sm:block">{{ $v->created_at->diffForHumans() }}</span>
                    </a></li>
                @endforeach
            </ul>
        @endif
    </div>
</x-app>
