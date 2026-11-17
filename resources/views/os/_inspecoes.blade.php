@php use App\Models\OrdemServicoInspecao; @endphp
@php $registros = $registros ?? $os->inspecoes; @endphp
@if ($registros->isNotEmpty())
    <ul class="space-y-3">
        @foreach ($registros as $inspecao)
            @php
                $item = $os->itens->find($inspecao->item_id);
                $foto = $os->fotos->find($inspecao->foto_id);
                $url = $foto ? ($publico ? route('orcamento.foto', [$os->token, $foto->id]) : route('os.fotos.show', [$os, $foto])) : null;
            @endphp
            <li class="rounded-md border border-linha p-3">
                <strong>{{ $inspecao->componente }}</strong>
                <span @class(['ml-2 font-medium', 'text-ok' => $inspecao->gravidade === 'ok', 'text-sinal-escuro' => $inspecao->gravidade === 'acompanhar', 'text-erro' => $inspecao->gravidade === 'imediata'])>{{ OrdemServicoInspecao::GRAVIDADES[$inspecao->gravidade] }}</span>
                @if ($inspecao->observacao)<p class="whitespace-pre-line">{{ $inspecao->observacao }}</p>@endif
                @if ($item && empty($dentroDoItem))<p class="text-sm">No orçamento: {{ $item->descricao }} · {{ \App\Support\Dinheiro::formatar($item->total_centavos) }}</p>@endif
                @if ($inspecao->revisar_em || $inspecao->revisar_km)
                    <p class="text-sm text-suave">Revisão sugerida {{ $inspecao->revisar_em ? 'em '.$inspecao->revisar_em->format('d/m/Y') : '' }} {{ $inspecao->revisar_km ? 'aos '.number_format($inspecao->revisar_km, 0, ',', '.').' km' : '' }}</p>
                @endif
                @if ($url)<a href="{{ $url }}" target="_blank" rel="noopener"><img class="mt-2 max-h-44 rounded object-contain" src="{{ $url }}" alt="Foto de {{ $inspecao->componente }}" loading="lazy"></a>@endif
                @if (! $publico && $inspecao->gravidade !== 'ok')
                    @can('agenda.editar')
                        <form method="post" action="{{ route('os.inspecao.lembrete', [$os, $inspecao]) }}" class="mt-3 flex flex-wrap items-end gap-2">
                            @csrf
                            <label class="campo"><span>Data de contato</span><input class="input" type="date" name="data_prevista" value="{{ $inspecao->revisar_em?->toDateString() }}" required></label>
                            <button class="btn btn-sm">Adicionar à fila de revisões</button>
                        </form>
                    @endcan
                @endif
            </li>
        @endforeach
    </ul>
@endif
