@props(['campo'])

<div {{ $attributes }} data-leitor-barras="{{ $campo }}">
    <label class="btn cursor-pointer focus-within:ring-2 focus-within:ring-sinal">
        <input type="file" accept="image/*" capture="environment" class="sr-only" aria-label="Escanear código de barras" data-foto-barras>
        Escanear código
    </label>
    <p class="mt-1 text-sm text-suave" data-status-barras aria-live="polite"></p>
</div>
