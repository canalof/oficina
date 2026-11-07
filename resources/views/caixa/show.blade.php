@php use App\Support\Dinheiro; @endphp
<x-app titulo="Caixa">
    <h1 class="mb-1">Caixa de {{ $caixa->aberto_em->format('d/m/Y') }}</h1>
    <p class="mb-5 text-suave">Aberto {{ $caixa->aberto_em->format('H:i') }} por {{ $caixa->abertoPor?->name }}@if($caixa->fechado_em) · fechado {{ $caixa->fechado_em->format('d/m H:i') }} por {{ $caixa->fechadoPor?->name }}@endif</p>
    @if ($caixa->fechado_em)
        @php $dif = (int) $caixa->dinheiro_contado_centavos - (int) $caixa->dinheiro_esperado_centavos; @endphp
        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="card card-corpo"><div class="text-sm text-suave">Esperado na gaveta</div><div class="mt-1 text-2xl font-semibold">{{ Dinheiro::formatar($caixa->dinheiro_esperado_centavos) }}</div></div>
            <div class="card card-corpo"><div class="text-sm text-suave">Contado</div><div class="mt-1 text-2xl font-semibold">{{ Dinheiro::formatar($caixa->dinheiro_contado_centavos) }}</div></div>
            <div class="card card-corpo"><div class="text-sm text-suave">Diferença</div><div @class(['mt-1 text-2xl font-semibold', 'text-ok' => $dif === 0, 'text-erro' => $dif !== 0])>{{ $dif === 0 ? 'Bateu' : ($dif > 0 ? '+' : '−').' '.Dinheiro::formatar(abs($dif)) }}</div></div>
        </div>
        @if ($caixa->observacao)<p class="mb-4 text-[15px]">Observação: {{ $caixa->observacao }}</p>@endif
    @endif
    <section class="card overflow-x-auto">@include('caixa._movimentos')</section>
    <a href="{{ route('caixa.index') }}" class="btn mt-6">Voltar ao caixa</a>
</x-app>
