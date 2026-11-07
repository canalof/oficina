<nav class="mb-5 flex flex-wrap gap-1 border-b border-linha">
    @foreach (['plataforma.index' => 'Oficinas', 'plataforma.planos' => 'Planos', 'plataforma.webhooks' => 'Avisos do gateway'] as $r => $t)
        <a href="{{ route($r) }}" @class(['-mb-px border-b-[3px] px-4 py-2.5 text-[15px] font-semibold', 'border-sinal' => request()->routeIs($r), 'border-transparent text-suave hover:text-texto' => ! request()->routeIs($r)])>{{ $t }}</a>
    @endforeach
</nav>
