<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TORQUE · Documento {{ $documento->id }}</title>
    <style>
        body { max-width: 760px; margin: 2rem auto; padding: 0 1rem; color: #1d2730; font: 16px/1.5 system-ui, sans-serif; }
        nav { display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem; }
        .texto { white-space: pre-wrap; overflow-wrap: anywhere; }
        .assinaturas { display: flex; gap: 2rem; margin-top: 5rem; break-inside: avoid; }
        .assinaturas div { flex: 1; border-top: 1px solid; padding-top: .4rem; }
        @media print { nav { display: none; } body { max-width: none; margin: 0; padding: 0; } }
    </style>
</head>
<body>
    <nav><a href="{{ route('mobilidade.documentos') }}">Contratos e vistorias</a><button onclick="window.print()">Imprimir / salvar PDF</button></nav>
    <main>
        <h1>TORQUE · Documento #{{ $documento->id }}</h1>
        <p>Gerado em {{ $documento->created_at->format('d/m/Y H:i') }}</p>
        <div class="texto">{{ $documento->texto }}</div>
        @if ($documento->aceite_nome)<p>Aceite presencial registrado em nome de {{ $documento->aceite_nome }}.</p>@endif
        <div class="assinaturas"><div>Responsável pela oficina</div><div>Cliente ou condutor</div></div>
    </main>
</body>
</html>
