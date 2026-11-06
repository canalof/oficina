<x-app titulo="Painel">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            @php($h = now()->hour)
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

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card card-corpo"><div class="text-sm text-suave">Clientes</div><div class="mt-1 text-3xl font-semibold">{{ number_format($totalClientes, 0, ',', '.') }}</div><div class="text-sm text-suave">+{{ $clientesMes }} neste mês</div></div>
        <div class="card card-corpo"><div class="text-sm text-suave">Veículos</div><div class="mt-1 text-3xl font-semibold">{{ number_format($totalVeiculos, 0, ',', '.') }}</div><div class="text-sm text-suave">+{{ $veiculosMes }} neste mês</div></div>
        <div class="card card-corpo"><div class="text-sm text-suave">Consultas de placa no mês</div><div class="mt-1 text-3xl font-semibold">{{ $consultasMes }}</div><div class="text-sm text-suave">placas novas consultadas</div></div>
        <div class="card card-corpo"><div class="text-sm text-suave">Gasto com consulta de placa</div><div class="mt-1 text-3xl font-semibold">R$ {{ number_format($gastoPlacaMes / 100, 2, ',', '.') }}</div><div class="text-sm text-suave">neste mês</div></div>
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
