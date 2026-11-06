@props(['placa', 'tamanho' => 'sm'])
@php $p = \App\Support\Placa::normalizar($placa); @endphp
<span {{ $attributes->merge(['class' => 'placa placa-'.$tamanho]) }} title="Placa {{ \App\Support\Placa::formatar($p) }}">
    <span class="placa-faixa" aria-hidden="true">@if($tamanho !== 'sm')<span>BR</span><span>BRASIL</span><span></span>@endif</span>
    <span class="placa-numero">{{ \App\Support\Placa::formatar($p) }}</span>
</span>
