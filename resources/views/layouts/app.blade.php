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
        ['painel', 'Painel', 'M3 13h8V3H3zm10 8h8V11h-8zM3 21h8v-6H3zm10-18v6h8V3z', null],
        ['recepcao', 'Recepção', 'M4 7h16M4 12h16M4 17h10', 'veiculos.ver'],
        ['clientes.index', 'Clientes', 'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0zM4 21c0-4 4-6 8-6s8 2 8 6', 'clientes.ver'],
        ['veiculos.index', 'Veículos', 'M5 16h14l-1.5-5.5A2 2 0 0 0 15.6 9H8.4a2 2 0 0 0-1.9 1.5zM7 16v2M17 16v2M7.5 13h.01M16.5 13h.01', 'veiculos.ver'],
        ['usuarios.index', 'Usuários', 'M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM3 20c0-3 3-5 6-5s6 2 6 5M17 11a3 3 0 0 0 0-6M21 20c0-2-1.5-4-4-4.7', 'usuarios.gerenciar'],
        ['empresa.edit', 'Empresa', 'M4 21V5l8-2 8 2v16M9 21v-4h6v4M8 9h.01M12 9h.01M16 9h.01M8 13h.01M12 13h.01M16 13h.01', 'empresa.configurar'],
    ];
@endphp
<div class="lg:flex">
    <aside class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full bg-asfalto text-[#c5ced6] transition-transform lg:sticky lg:top-0 lg:h-screen lg:shrink-0 lg:translate-x-0 [.menu-aberto_&]:translate-x-0" aria-label="Menu principal">
        <div class="flex h-16 items-center gap-3 border-b border-asfalto-2 px-5">
            <svg width="30" height="30" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="7" fill="#f2a900"/><path d="M8 11h16M16 11v13" stroke="#1f2a33" stroke-width="4" stroke-linecap="round"/></svg>
            <div>
                <div class="text-lg font-bold leading-none tracking-tight text-white">Torque</div>
                <div class="mt-1 max-w-[160px] truncate text-xs text-[#8d9aa6]">{{ auth()->user()->empresa->nome_fantasia }}</div>
            </div>
        </div>
        <nav class="p-3">
            @foreach ($menu as [$rota, $rotulo, $icone, $perm])
                @if (! $perm || auth()->user()->can($perm))
                    @php $ativo = request()->routeIs(str_replace('.index', '', $rota).'*'); @endphp
                    <a href="{{ route($rota) }}" @class([
                        'relative mb-0.5 flex items-center gap-3 rounded-md px-3 py-2.5 text-[15px] font-medium hover:bg-asfalto-2 hover:text-white',
                        'bg-asfalto-2 text-white before:absolute before:left-0 before:top-2 before:bottom-2 before:w-1 before:rounded-r before:bg-sinal' => $ativo,
                    ]) @if($ativo) aria-current="page" @endif>
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icone }}"/></svg>
                        {{ $rotulo }}
                    </a>
                @endif
            @endforeach
        </nav>
        <div class="absolute bottom-0 left-0 right-0 border-t border-asfalto-2 p-4 text-sm">
            <div class="truncate font-semibold text-white">{{ auth()->user()->name }}</div>
            <div class="text-xs text-[#8d9aa6]">{{ auth()->user()->perfil->rotulo() }}</div>
            <div class="mt-3 flex gap-4">
                <a href="{{ route('senha') }}" class="hover:text-white">Trocar senha</a>
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
            @include('partials.flash')
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
