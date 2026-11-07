@props(['titulo'])
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $titulo }} · Torque</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-fundo">
    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-5 py-10">
        <a href="{{ route('login') }}" class="mb-8 flex items-center gap-3">
            <svg width="34" height="34" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="7" fill="#f2a900"/><path d="M8 11h16M16 11v13" stroke="#1f2a33" stroke-width="4" stroke-linecap="round"/></svg>
            <span class="text-2xl font-bold tracking-tight">Torque</span>
        </a>
        @include('partials.flash')
        {{ $slot }}
    </main>
</body>
</html>
