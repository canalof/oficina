<nav class="mb-5 flex flex-wrap gap-1 border-b border-linha" aria-label="Filtro">
    @foreach ($abas as $chave => $rotulo)
        <a href="{{ request()->url() }}?filtro={{ $chave }}" @class([
            '-mb-px border-b-[3px] px-4 py-2.5 text-[15px] font-semibold',
            'border-sinal text-texto' => $filtro === $chave,
            'border-transparent text-suave hover:text-texto' => $filtro !== $chave,
        ])>{{ $rotulo }}</a>
    @endforeach
</nav>
