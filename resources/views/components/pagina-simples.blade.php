@props(['titulo'])
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $titulo }} · Torque</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @include('partials.pwa')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-fundo">
    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-5 py-10">
        <a href="{{ route('login') }}" class="mb-8 flex items-center gap-3">
            <img src="/favicon.svg" width="34" height="34" class="rounded-md" alt="">
            <span class="text-2xl font-bold tracking-tight">TORQUE</span>
        </a>
        @include('partials.flash')
        {{ $slot }}
    </main>
</body>
</html>
