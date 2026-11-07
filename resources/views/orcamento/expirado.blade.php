<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>Link expirado · {{ $empresa->nome_fantasia }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen">
    <header class="bg-asfalto text-white"><div class="mx-auto max-w-2xl px-4 py-4 text-lg font-bold">{{ $empresa->nome_fantasia }}</div></header>
    <main class="mx-auto max-w-2xl px-4 py-10">
        <h1 class="text-xl">Este link de orçamento não vale mais</h1>
        <p class="mt-2 text-[15px] text-suave">O prazo passou ou a oficina gerou um link novo. Peça o link atualizado{{ $empresa->telefone ? ' pelo '.$empresa->telefone : '' }}.</p>
    </main>
</body>
</html>
