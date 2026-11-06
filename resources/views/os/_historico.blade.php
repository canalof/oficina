{{-- Lista de OS de um veículo ou cliente: $ordens (com itens), $mostrarPlaca --}}
@php use App\Support\Dinheiro; @endphp
@if ($ordens->isEmpty())
    <p class="px-5 py-8 text-center text-suave">Nenhuma ordem de serviço ainda.</p>
@else
    <ul class="divide-y divide-linha">
        @foreach ($ordens as $o)
            @php $recusados = $o->itens->where('situacao', 'recusado'); @endphp
            <li class="px-5 py-3">
                <a href="{{ route('os.show', $o) }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 hover:underline">
                    @if ($mostrarPlaca ?? false)<x-placa :placa="$o->veiculo->placa" />@endif
                    <span class="font-semibold">OS {{ $o->numeroFormatado() }}</span>
                    <span class="text-sm text-suave">{{ $o->created_at->format('d/m/Y') }}{{ $o->km_entrada ? ' · '.number_format($o->km_entrada, 0, ',', '.').' km' : '' }}</span>
                    <span class="rotulo {{ $o->status->cor() }}">{{ $o->status->rotulo() }}</span>
                    <span class="ml-auto font-semibold">{{ Dinheiro::formatar($o->total_centavos) }}</span>
                </a>
                @php $feitos = $o->itens->where('situacao', 'aprovado'); @endphp
                @if ($feitos->isNotEmpty())<p class="mt-1 text-sm">{{ $feitos->pluck('descricao')->join(', ') }}</p>@endif
                @if ($recusados->isNotEmpty())<p class="mt-0.5 text-sm text-sinal-escuro">Recusado: {{ $recusados->pluck('descricao')->join(', ') }}</p>@endif
                @if ($o->garantiaAte() && $o->garantiaAte()->isFuture())<p class="mt-0.5 text-sm text-ok">Na garantia até {{ $o->garantiaAte()->format('d/m/Y') }}</p>@endif
            </li>
        @endforeach
    </ul>
@endif
