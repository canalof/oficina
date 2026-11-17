<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $titulo ?? 'Torque' }} · {{ auth()->user()->empresa->nome_fantasia }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @include('partials.pwa')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
@php
    // [rota, rótulo, ícone, permissão, módulo opcional, outras rotas que acendem o item]. Grupos ficam recolhidos fora do item ativo.
    $menu = [
        null => [
            ['painel', 'Início', 'M3 13h8V3H3zm10 8h8V11h-8zM3 21h8v-6H3zm10-18v6h8V3z', null, null],
            ['recepcao', 'Recepção', 'M4 7h16M4 12h16M4 17h10', 'veiculos.ver', null],
            ['os.index', 'Ordens de serviço', 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 12h6M9 16h4', 'os.ver', null],
            ['agenda.index', 'Agenda', 'M7 3v4M17 3v4M4 9h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z', 'agenda.ver', null, 'lembretes*'],
            ['clientes.index', 'Clientes', 'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0zM4 21c0-4 4-6 8-6s8 2 8 6', 'clientes.ver', null],
            ['veiculos.index', 'Veículos', 'M5 16h14l-1.5-5.5A2 2 0 0 0 15.6 9H8.4a2 2 0 0 0-1.9 1.5zM7 16v2M17 16v2M7.5 13h.01M16.5 13h.01', 'veiculos.ver', null],
        ],
        'Estoque' => [
            ['produtos.index', 'Produtos', 'M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8M12 13v8', 'estoque.ver', null],
            ['compras.index', 'Notas de compra', 'M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM14 3v6h6M8 13h8M8 17h5', 'estoque.ver', null],
            ['fornecedores.index', 'Fornecedores', 'M3 7h11v9H3zM14 10h4l3 3v3h-7M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM17 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z', 'estoque.ver', null],
            ['servicos.index', 'Catálogo de serviços', 'M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18v3h3l6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.4-.6-.6-2.4z', 'os.ver', null],
        ],
        'Dinheiro' => [
            ['caixa.index', 'Caixa', 'M3 7h18v12H3zM3 11h18M7 15h3', 'caixa.operar', null],
            ['receber.index', 'Contas a receber', 'M12 3v18M17 8l-5-5-5 5', 'financeiro.ver', null],
            ['pagar.index', 'Contas a pagar', 'M12 21V3M7 16l5 5 5-5', 'financeiro.ver', null],
            ['relatorio.index', 'Resumo do mês', 'M4 20V10M10 20V4M16 20v-7M22 20H2', 'financeiro.ver', null],
            ['bancos.index', 'Bancos', 'M3 9l9-5 9 5M4 20h16M6 10v7M12 10v7M18 10v7', 'financeiro.ver', 'bancos'],
            ['pix.index', 'Pix', 'M12 3l9 9-9 9-9-9zM8 12h8', 'financeiro.ver', 'bancos'],
            ['comissoes.index', 'Comissões', 'M19 5L5 19M6.5 9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5zM17.5 20a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z', 'comissoes.ver', 'comissoes'],
            ['notas.index', 'Notas fiscais', 'M6 2h9l5 5v15H6zM14 2v6h6M9 13h6M9 17h6M9 9h2', 'notas.emitir', 'fiscal'],
        ],
        'Condutores e locação' => [
            ['mobilidade.index', 'Condutores e retirada', 'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0zM9 21l3-3 3 3M12 18v-3', 'mobilidade.ver', 'condutores'],
            ['locacao.index', 'Reservas e frota', 'M5 16h14l-1.5-5.5A2 2 0 0 0 15.6 9H8.4a2 2 0 0 0-1.9 1.5zM7 16v2M17 16v2M3 5h4M5 3v4', 'locacao.ver', 'locacao'],
        ],
        'Ajustes' => [
            ['empresa.edit', 'Empresa e módulos', 'M4 21V5l8-2 8 2v16M9 21v-4h6v4M8 9h.01M12 9h.01M16 9h.01M8 13h.01M12 13h.01M16 13h.01', 'empresa.configurar', null],
            ['usuarios.index', 'Usuários', 'M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM3 20c0-3 3-5 6-5s6 2 6 5M17 11a3 3 0 0 0 0-6M21 20c0-2-1.5-4-4-4.7', 'usuarios.gerenciar', null],
            ['assinatura', 'Assinatura', 'M2 7h20v12H2zM2 11h20M6 15h4', 'empresa.configurar', null],
            ['cnh.integracoes.index', 'Integrações de CNH', 'M8 12h8M10 8l2-2a4 4 0 0 1 6 6l-2 2M14 16l-2 2a4 4 0 0 1-6-6l2-2', 'empresa.configurar', 'condutores'],
            ['plataforma.index', 'Plataforma', 'M12 2l9 5-9 5-9-5 9-5zM3 12l9 5 9-5M3 17l9 5 9-5', 'plataforma', null],
        ],
    ];
@endphp
<div class="lg:flex">
    <aside class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-asfalto text-[#c5ced6] transition-transform lg:sticky lg:top-0 lg:h-screen lg:shrink-0 lg:translate-x-0 [.menu-aberto_&]:translate-x-0" aria-label="Menu principal">
        <div class="flex h-16 shrink-0 items-center gap-3 border-b border-asfalto-2 px-5">
            <img src="/favicon.svg" width="30" height="30" class="rounded-md" alt="">
            <div>
                <div class="text-lg font-bold leading-none tracking-tight text-white">TORQUE</div>
                <div class="mt-1 max-w-[160px] truncate text-xs text-[#8d9aa6]">{{ auth()->user()->empresa->nome_fantasia }}</div>
            </div>
        </div>
        <nav class="min-h-0 flex-1 overflow-y-auto p-3">
            @if (auth()->user()->admin_plataforma)
                @php $menu = [null => [['plataforma.index', 'Plataforma', 'M12 2l9 5-9 5-9-5 9-5zM3 12l9 5 9-5M3 17l9 5 9-5', null], ['plataforma.planos', 'Planos', 'M2 7h20v12H2zM2 11h20', null], ['seguranca', 'Segurança', 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z', null]]]; @endphp
            @endif
            @foreach ($menu as $grupo => $itens)
                @php
                    $empresaMenu = auth()->user()->empresa;
                    $visiveis = collect($itens)->filter(fn ($i) => (! $i[3] || auth()->user()->can($i[3])) && (! ($i[4] ?? null) || $empresaMenu?->temModulo($i[4])));
                    $ativoEm = fn ($i) => request()->routeIs(str_replace('.index', '', $i[0]).'*') || request()->routeIs($i[0]) || (($i[5] ?? null) && request()->routeIs($i[5]));
                    $grupoAtivo = $visiveis->contains(fn ($i) => $ativoEm($i));
                @endphp
                @continue($visiveis->isEmpty())
                @if ($grupo)<details class="group/menu mt-2" @if ($grupoAtivo) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between rounded-md px-3 py-2 text-xs font-semibold uppercase tracking-wide text-[#8d9aa6] hover:text-white">{{ $grupo }}<svg class="h-4 w-4 transition-transform group-open/menu:rotate-90" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></summary>
                @endif
                @foreach ($visiveis as $item)
                    @php [$rota, $rotulo, $icone] = $item; $ativo = $ativoEm($item); @endphp
                    <a href="{{ route($rota) }}" @class([
                        'relative mb-0.5 flex items-center gap-3 rounded-md px-3 py-2 text-[15px] font-medium hover:bg-asfalto-2 hover:text-white',
                        'bg-asfalto-2 text-white before:absolute before:left-0 before:top-2 before:bottom-2 before:w-1 before:rounded-r before:bg-sinal' => $ativo,
                    ]) @if($ativo) aria-current="page" @endif>
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icone }}"/></svg>
                        {{ $rotulo }}
                    </a>
                @endforeach
                @if ($grupo)</details>@endif
            @endforeach
        </nav>
        <div class="shrink-0 border-t border-asfalto-2 p-4 text-sm">
            <div class="truncate font-semibold text-white">{{ auth()->user()->name }}</div>
            <div class="text-xs text-[#8d9aa6]">{{ auth()->user()->perfil->rotulo() }}</div>
            <div class="mt-3 flex gap-4">
                <a href="{{ route('tutorial') }}" class="hover:text-white">Ajuda</a>
                <a href="{{ route('senha') }}" class="hover:text-white">Senha</a>
                <a href="{{ route('seguranca') }}" class="hover:text-white">Segurança</a>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="hover:text-white">Sair</button></form>
            </div>
        </div>
    </aside>
    <div class="fixed inset-0 z-30 hidden bg-black/40 [.menu-aberto_&]:block lg:hidden" data-menu-abrir></div>

    <div class="min-w-0 flex-1">
        <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-linha bg-white px-4 lg:hidden">
            <button class="rounded-md p-2 hover:bg-fundo" data-menu-abrir aria-label="Abrir menu">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <span class="font-bold">Torque</span>
            @can('veiculos.ver')<a href="{{ route('recepcao') }}" class="btn-sinal btn-sm ml-auto">Recepção</a>@endcan
        </header>
        <main class="mx-auto max-w-6xl px-4 py-6 lg:px-8 lg:py-8">
            @php $emp = auth()->user()->empresa; @endphp
            @if (! auth()->user()->admin_plataforma && $emp && ! request()->routeIs('assinatura'))
                @if ($emp->assinatura_status === 'trial' && $emp->diasDeTeste() !== null && $emp->diasDeTeste() <= 5)
                    <a href="{{ route('assinatura') }}" class="mb-5 block rounded-md border border-[#f0d58a] bg-[#fff7df] px-4 py-2.5 text-[15px] hover:border-sinal">Seu teste grátis termina em {{ $emp->diasDeTeste() }} dia(s). <strong>Escolher plano</strong></a>
                @elseif ($emp->assinatura_status === 'inadimplente')
                    <a href="{{ route('assinatura') }}" class="mb-5 block rounded-md border border-[#ecc3bf] bg-[#fcf0ef] px-4 py-2.5 text-[15px] text-[#8c241d]">Há uma fatura do Torque em atraso. Regularize para não ter o acesso suspenso. <strong>Ver fatura</strong></a>
                @endif
            @endif
            @include('partials.flash')
            @isset($slot)
                {{ $slot }}
            @else
                @yield('content')
            @endisset
        </main>
    </div>
</div>
</body>
</html>
