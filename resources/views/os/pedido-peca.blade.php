<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pedido de compra · OS {{ $os->numeroFormatado() }}</title>
    @vite(['resources/css/app.css'])
</head>
<body>
    <main class="mx-auto max-w-2xl p-5">
        <div class="mb-4 flex gap-2 print:hidden">
            <a class="btn" href="{{ route('os.show', $os) }}#operacao-os">Voltar à OS</a>
            <button class="btn" type="button" onclick="window.print()">Imprimir / salvar PDF</button>
        </div>
        <section class="card card-corpo space-y-2">
            <h1>Pedido de compra</h1>
            <p>{{ auth()->user()->empresa->nome_fantasia }} · OS {{ $os->numeroFormatado() }}</p>
            <p>Fornecedor <strong>{{ $cotacao['fornecedor'] }}</strong></p>
            <p>Peça <strong>{{ $peca->descricao }}</strong> · Quantidade {{ $peca->quantidadeFormatada() }}</p>
            <p>Peças {{ \App\Support\Dinheiro::formatar((int) $cotacao['valor']) }} · Frete {{ \App\Support\Dinheiro::formatar((int) ($cotacao['frete'] ?? 0)) }}</p>
            <p>Total {{ \App\Support\Dinheiro::formatar((int) $cotacao['valor'] + (int) ($cotacao['frete'] ?? 0)) }}</p>
            @if ($cotacao['previsao'] ?? null)<p>Previsão de chegada {{ \Carbon\Carbon::parse($cotacao['previsao'])->format('d/m/Y') }}</p>@endif
        </section>
    </main>
</body>
</html>
