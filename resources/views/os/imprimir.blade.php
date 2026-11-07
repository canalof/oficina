@php use App\Support\Dinheiro; use App\Support\Checklist; use App\Support\Documento; use App\Support\StatusOs; @endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>OS {{ $os->numeroFormatado() }} · {{ $empresa->nome_fantasia }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 12mm; }
        @media print { .nao-imprimir { display: none !important; } body { background: #fff; } .folha { box-shadow: none; border: 0; padding: 0; } }
        body { font-size: 13px; }
    </style>
</head>
<body class="bg-fundo">
    <div class="nao-imprimir mx-auto flex max-w-[210mm] gap-2 px-4 py-4">
        <button onclick="window.print()" class="btn-primario">Imprimir</button>
        <a href="{{ route('os.show', $os) }}" class="btn">Voltar para a OS</a>
    </div>

    <main class="folha mx-auto mb-10 max-w-[210mm] border border-linha bg-white p-[12mm]">
        <header class="flex items-start justify-between gap-6 border-b-2 border-asfalto pb-3">
            <div>
                <div class="text-xl font-bold">{{ $empresa->nome_fantasia }}</div>
                <div class="text-suave">
                    {{ $empresa->razao_social }}{{ $empresa->cnpj ? ' · CNPJ '.Documento::formatar($empresa->cnpj) : '' }}<br>
                    @if ($loja && $loja->logradouro){{ $loja->logradouro }}{{ $loja->numero ? ', '.$loja->numero : '' }} · @endif{{ collect([$empresa->cidade, $empresa->uf])->filter()->join('/') }}
                    {{ $empresa->telefone ? ' · '.$empresa->telefone : '' }}
                </div>
            </div>
            <div class="text-right">
                <div class="text-xs uppercase tracking-wide text-suave">{{ in_array($os->status, [StatusOs::Orcamento, StatusOs::Aguardando], true) ? 'Orçamento' : 'Ordem de serviço' }}</div>
                <div class="text-2xl font-bold">Nº {{ $os->numeroFormatado() }}</div>
                <div class="text-suave">{{ $os->created_at->format('d/m/Y H:i') }}</div>
            </div>
        </header>

        <section class="mt-4 grid grid-cols-2 gap-6">
            <div>
                <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-suave">Cliente</h2>
                <div class="font-semibold">{{ $os->cliente?->nome ?? '—' }}</div>
                @if ($os->cliente)
                    <div>{{ $os->cliente->documentoFormatado() }}</div>
                    <div>{{ $os->cliente->whatsapp ?: $os->cliente->telefone }}</div>
                    <div>{{ $os->cliente->enderecoCompleto() }}</div>
                @endif
            </div>
            <div>
                <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-suave">Veículo</h2>
                <div class="font-semibold">{{ $os->veiculo->placaFormatada() }} · {{ $os->veiculo->descricao() }}</div>
                <div>{{ collect([$os->veiculo->ano_fabricacao ? $os->veiculo->ano_fabricacao.'/'.$os->veiculo->ano_modelo : null, $os->veiculo->cor])->filter()->join(' · ') }}</div>
                <div>Km: {{ $os->km_entrada ? number_format($os->km_entrada, 0, ',', '.') : '—' }} · Combustível: {{ $os->combustivel !== null ? Checklist::COMBUSTIVEL[$os->combustivel] : '—' }}</div>
                @if ($os->previsao_entrega)<div>Previsão de entrega: {{ $os->previsao_entrega->format('d/m/Y H:i') }}</div>@endif
            </div>
        </section>

        @if ($os->defeito_relatado || $os->diagnostico)
            <section class="mt-4 grid grid-cols-2 gap-6">
                @if ($os->defeito_relatado)<div><h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-suave">Relato do cliente</h2><p class="whitespace-pre-line">{{ $os->defeito_relatado }}</p></div>@endif
                @if ($os->diagnostico)<div><h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-suave">Diagnóstico</h2><p class="whitespace-pre-line">{{ $os->diagnostico }}</p></div>@endif
            </section>
        @endif

        <table class="mt-5 w-full border-collapse">
            <thead>
                <tr class="border-b-2 border-asfalto text-left text-xs uppercase tracking-wide text-suave">
                    <th class="py-1.5">Descrição</th><th class="py-1.5">Tipo</th><th class="py-1.5 text-right">Qtd</th><th class="py-1.5 text-right">Unitário</th><th class="py-1.5 text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($os->itens->where('situacao', '<>', 'recusado') as $item)
                    <tr class="border-b border-linha">
                        <td class="py-1.5">{{ $item->descricao }}@if($item->situacao === 'pendente') <span class="text-suave">(a aprovar)</span>@endif</td>
                        <td class="py-1.5">{{ $item->tipo === 'peca' ? 'Peça' : 'Serviço' }}</td>
                        <td class="py-1.5 text-right">{{ $item->quantidadeFormatada() }}</td>
                        <td class="py-1.5 text-right">{{ Dinheiro::formatar($item->valor_unitario_centavos) }}</td>
                        <td class="py-1.5 text-right">{{ Dinheiro::formatar($item->total_centavos) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <dl class="ml-auto mt-3 w-64 space-y-0.5">
            <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ Dinheiro::formatar($os->subtotal()) }}</dd></div>
            @if ($os->desconto_centavos)<div class="flex justify-between"><dt>Desconto</dt><dd>− {{ Dinheiro::formatar($os->desconto_centavos) }}</dd></div>@endif
            <div class="flex justify-between border-t-2 border-asfalto pt-1 text-base font-bold"><dt>Total</dt><dd>{{ $os->totalFormatado() }}</dd></div>
        </dl>

        @php $recusados = $os->itens->where('situacao', 'recusado'); @endphp
        @if ($recusados->isNotEmpty())
            <p class="mt-3 text-suave">Itens recomendados e não aprovados pelo cliente: {{ $recusados->pluck('descricao')->join(', ') }}.</p>
        @endif

        @if ($os->checklist)
            <section class="mt-5">
                <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-suave">Checklist de entrada</h2>
                <div class="grid grid-cols-3 gap-x-4 gap-y-0.5">
                    @foreach (Checklist::ITENS as $k => $rot)
                        @isset($os->checklist[$k])
                            @php $e = $os->checklist[$k]; @endphp
                            <div>{{ $rot }}: <strong @class(['text-erro' => $e['estado'] === 'avaria'])>{{ Checklist::ESTADOS[$e['estado']] }}</strong>{{ $e['obs'] ? ' ('.$e['obs'].')' : '' }}</div>
                        @endisset
                    @endforeach
                </div>
            </section>
        @endif

        <p class="mt-5 text-suave">
            Garantia de {{ $os->garantia_dias }} dias para os serviços executados, contados da entrega do veículo.
            @if ($os->entregue_em) Entregue em {{ $os->entregue_em->format('d/m/Y') }}; garantia até {{ $os->garantiaAte()->format('d/m/Y') }}.@endif
           
        </p>

        <div class="mt-14 grid grid-cols-2 gap-10 text-center">
            <div class="border-t border-texto pt-1">{{ $os->cliente?->nome ?? 'Cliente' }}</div>
            <div class="border-t border-texto pt-1">{{ $empresa->nome_fantasia }}</div>
        </div>
    </main>
</body>
</html>
