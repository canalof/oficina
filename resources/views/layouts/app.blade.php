<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $titulo ?? 'Torque' }} · {{ auth()->user()->empresa->nome_fantasia }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
@php
    $menu = [
        null => [
            ['painel', 'Painel', 'M3 13h8V3H3zm10 8h8V11h-8zM3 21h8v-6H3zm10-18v6h8V3z', null],
            ['recepcao', 'Recepção', 'M4 7h16M4 12h16M4 17h10', 'veiculos.ver'],
            ['agenda.index', 'Agenda', 'M7 3v4M17 3v4M4 9h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z', 'agenda.ver'],
            ['os.index', 'Ordens de serviço', 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 12h6M9 16h4', 'os.ver'],
            ['clientes.index', 'Clientes', 'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0zM4 21c0-4 4-6 8-6s8 2 8 6', 'clientes.ver'],
            ['veiculos.index', 'Veículos', 'M5 16h14l-1.5-5.5A2 2 0 0 0 15.6 9H8.4a2 2 0 0 0-1.9 1.5zM7 16v2M17 16v2M7.5 13h.01M16.5 13h.01', 'veiculos.ver'],
            ['lembretes.index', 'Lembretes de revisão', 'M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0', 'agenda.ver'],
        ],
        'Estoque' => [
            ['produtos.index', 'Produtos', 'M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8M12 13v8', 'estoque.ver'],
            ['compras.index', 'Notas de compra', 'M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM14 3v6h6M8 13h8M8 17h5', 'estoque.ver'],
            ['fornecedores.index', 'Fornecedores', 'M3 7h11v9H3zM14 10h4l3 3v3h-7M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM17 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z', 'estoque.ver'],
            ['servicos.index', 'Catálogo de serviços', 'M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18v3h3l6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.4-.6-.6-2.4z', 'os.ver'],
        ],
        'Dinheiro' => [
            ['caixa.index', 'Caixa', 'M3 7h18v12H3zM3 11h18M7 15h3', 'caixa.operar'],
            ['receber.index', 'Contas a receber', 'M12 3v18M17 8l-5-5-5 5', 'financeiro.ver'],
            ['pagar.index', 'Contas a pagar', 'M12 21V3M7 16l5 5 5-5', 'financeiro.ver'],
            ['comissoes.index', 'Comissões', 'M19 5L5 19M6.5 9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5zM17.5 20a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z', 'comissoes.ver'],
            ['relatorio.index', 'Resumo do mês', 'M4 20V10M10 20V4M16 20v-7M22 20H2', 'financeiro.ver'],
            ['notas.index', 'Notas fiscais', 'M6 2h9l5 5v15H6zM14 2v6h6M9 13h6M9 17h6M9 9h2', 'notas.emitir'],
        ],
        'Ajustes' => [
            ['assinatura', 'Assinatura', 'M2 7h20v12H2zM2 11h20M6 15h4', 'empresa.configurar'],
            ['plataforma.index', 'Plataforma', 'M12 2l9 5-9 5-9-5 9-5zM3 12l9 5 9-5M3 17l9 5 9-5', 'plataforma'],
            ['usuarios.index', 'Usuários', 'M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM3 20c0-3 3-5 6-5s6 2 6 5M17 11a3 3 0 0 0 0-6M21 20c0-2-1.5-4-4-4.7', 'usuarios.gerenciar'],
            ['empresa.edit', 'Empresa', 'M4 21V5l8-2 8 2v16M9 21v-4h6v4M8 9h.01M12 9h.01M16 9h.01M8 13h.01M12 13h.01M16 13h.01', 'empresa.configurar'],
        ],
    ];
@endphp
<div class="lg:flex">
    <aside class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-asfalto text-[#c5ced6] transition-transform lg:sticky lg:top-0 lg:h-screen lg:shrink-0 lg:translate-x-0 [.menu-aberto_&]:translate-x-0" aria-label="Menu principal">
        <div class="flex h-16 shrink-0 items-center gap-3 border-b border-asfalto-2 px-5">
            <svg width="30" height="30" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="7" fill="#f2a900"/><path d="M8 11h16M16 11v13" stroke="#1f2a33" stroke-width="4" stroke-linecap="round"/></svg>
            <div>
                <div class="text-lg font-bold leading-none tracking-tight text-white">Torque</div>
                <div class="mt-1 max-w-[160px] truncate text-xs text-[#8d9aa6]">{{ auth()->user()->empresa->nome_fantasia }}</div>
            </div>
        </div>
        <nav class="min-h-0 flex-1 overflow-y-auto p-3">
            @if (auth()->user()->admin_plataforma)
                @php $menu = [null => [['plataforma.index', 'Plataforma', 'M12 2l9 5-9 5-9-5 9-5zM3 12l9 5 9-5M3 17l9 5 9-5', null], ['plataforma.planos', 'Planos', 'M2 7h20v12H2zM2 11h20', null], ['seguranca', 'Segurança', 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z', null]]]; @endphp
            @endif
            @foreach ($menu as $grupo => $itens)
                @php $visiveis = collect($itens)->filter(fn ($i) => ! $i[3] || auth()->user()->can($i[3])); @endphp
                @continue($visiveis->isEmpty())
                @if ($grupo)<div class="mb-1 mt-4 px-3 text-xs font-semibold text-[#8d9aa6]">{{ $grupo }}</div>@endif
                @foreach ($visiveis as [$rota, $rotulo, $icone, $perm])
                    @php $ativo = request()->routeIs(str_replace('.index', '', $rota).'*') || request()->routeIs($rota); @endphp
                    <a href="{{ route($rota) }}" @class([
                        'relative mb-0.5 flex items-center gap-3 rounded-md px-3 py-2 text-[15px] font-medium hover:bg-asfalto-2 hover:text-white',
                        'bg-asfalto-2 text-white before:absolute before:left-0 before:top-2 before:bottom-2 before:w-1 before:rounded-r before:bg-sinal' => $ativo,
                    ]) @if($ativo) aria-current="page" @endif>
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icone }}"/></svg>
                        {{ $rotulo }}
                    </a>
                @endforeach
            @endforeach
        </nav>
        <div class="shrink-0 border-t border-asfalto-2 p-4 text-sm">
            <div class="truncate font-semibold text-white">{{ auth()->user()->name }}</div>
            <div class="text-xs text-[#8d9aa6]">{{ auth()->user()->perfil->rotulo() }}</div>
            <div class="mt-3 flex gap-4">
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
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
