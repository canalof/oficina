<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Entrar · Torque</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-asfalto">
<div class="grid min-h-screen lg:grid-cols-[1.1fr_1fr]">
    <section class="relative hidden overflow-hidden lg:block" aria-hidden="true">
        {{-- faixas de pista --}}
        <svg class="absolute inset-0 h-full w-full" viewBox="0 0 800 900" preserveAspectRatio="xMidYMid slice">
            <rect width="800" height="900" fill="#1f2a33"/>
            <path d="M120 900 L360 0 M680 900 L440 0" stroke="#2c3a46" stroke-width="6"/>
            <g stroke="#f2a900" stroke-width="10" stroke-linecap="round">
                <path d="M400 860v-90M400 690v-90M400 520v-90M400 350v-90M400 180v-90"/>
            </g>
            <defs><linearGradient id="nevoa" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1f2a33" stop-opacity="0"/><stop offset=".55" stop-color="#1f2a33" stop-opacity=".94"/></linearGradient></defs>
            <rect y="420" width="800" height="480" fill="url(#nevoa)"/>
        </svg>
        <div class="absolute bottom-16 left-14 right-14 text-white">
            <div class="placa placa-md mb-8 scale-150 origin-bottom-left">
                <span class="placa-faixa"><span>BR</span><span>BRASIL</span><span></span></span>
                <span class="placa-numero">TRQ1A26</span>
            </div>
            <p class="max-w-md text-3xl font-semibold leading-tight">O carro chega, a placa é lida e a ficha abre sozinha.</p>
            <p class="mt-3 max-w-md text-[#a9b6c1]">Gestão para autocenters e oficinas: recepção, ordem de serviço, estoque, caixa e notas fiscais.</p>
        </div>
    </section>

    <section class="flex items-center justify-center bg-fundo px-5 py-12">
        <form method="post" action="{{ route('login.entrar') }}" class="w-full max-w-sm">
            @csrf
            <div class="mb-8 flex items-center gap-3">
                <svg width="38" height="38" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="7" fill="#f2a900"/><path d="M8 11h16M16 11v13" stroke="#1f2a33" stroke-width="4" stroke-linecap="round"/></svg>
                <span class="text-2xl font-bold tracking-tight">Torque</span>
            </div>
            @if (session('ok'))<div class="mb-4 rounded-md border border-[#b7dcc6] bg-[#eef8f2] px-3 py-2 text-[15px] text-[#185c35]" role="status">{{ session('ok') }}</div>@endif
            <h1 class="mb-1">Entrar</h1>
            <p class="mb-6 text-suave">Use o e-mail e a senha que o responsável da sua oficina cadastrou.</p>

            <x-campo nome="email" rotulo="E-mail" tipo="email" autocomplete="username" required autofocus class="mb-4" />
            <label class="campo mb-4">
                <span>Senha</span>
                <input type="password" name="password" class="input" autocomplete="current-password" required>
            </label>
            <label class="mb-6 flex items-center gap-2 text-[15px]">
                <input type="checkbox" name="lembrar" value="1" class="h-4 w-4 accent-asfalto"> Manter conectado neste aparelho
            </label>
            <button class="btn-primario w-full py-3">Entrar</button>
            <div class="mt-5 flex flex-wrap justify-between gap-3 text-[15px]">
                <a href="{{ route('password.request') }}" class="font-semibold text-asfalto-3 hover:underline">Esqueci a senha</a>
                <a href="{{ route('cadastro') }}" class="font-semibold text-asfalto-3 hover:underline">Teste grátis por 14 dias</a>
            </div>
        </form>
    </section>
</div>
</body>
</html>
