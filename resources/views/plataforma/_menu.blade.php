@php
    $pendente = \App\Models\Empresa::CADASTRO_PENDENTE;
    $abas = [
        ['plataforma.index', 'Oficinas', []],
        ['plataforma.index', 'Pendentes', ['status' => $pendente]],
        ['plataforma.planos', 'Planos', []],
        ['plataforma.webhooks', 'Avisos do gateway', []],
        ['tutorial', 'Ajuda da plataforma', []],
    ];
@endphp
<nav class="mb-5 flex flex-wrap gap-1 border-b border-linha" aria-label="Plataforma">
    @foreach ($abas as [$rota, $titulo, $parametros])
        @php $ativa = request()->routeIs($rota) && ($rota !== 'plataforma.index' || (request()->query('status') === $pendente) === isset($parametros['status'])); @endphp
        <a href="{{ route($rota, $parametros) }}" @class(['-mb-px border-b-[3px] px-4 py-2.5 text-[15px] font-semibold', 'border-sinal' => $ativa, 'border-transparent text-suave hover:text-texto' => ! $ativa]) @if($ativa) aria-current="page" @endif>{{ $titulo }}</a>
    @endforeach
</nav>
