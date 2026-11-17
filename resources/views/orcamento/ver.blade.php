@php
    use App\Support\Dinheiro;
    use App\Support\StatusOs;
    $pendentes = $os->itens->where('situacao', 'pendente');
    $aprovados = $os->itens->where('situacao', 'aprovado');
    $recusados = $os->itens->where('situacao', 'recusado');
    $nome = $os->cliente ? explode(' ', trim($os->cliente->nome))[0] : null;
    $complementar = $aprovados->isNotEmpty() && $pendentes->isNotEmpty();
    $inspecoesPorItem = $os->inspecoes->groupBy('item_id');
    $inspecoesGerais = $os->inspecoes->reject(fn ($i) => $pendentes->contains('id', $i->item_id));
    $fotosGerais = $os->fotos->whereNotIn('id', $os->inspecoes->pluck('foto_id')->filter());
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Orçamento {{ $os->numeroFormatado() }} · {{ $empresa->nome_fantasia }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <header class="bg-asfalto text-white">
        <div class="mx-auto max-w-2xl px-4 py-4">
            <div class="text-lg font-bold">{{ $empresa->nome_fantasia }}</div>
            <div class="text-sm text-[#c5ced6]">{{ collect([$empresa->cidade, $empresa->uf])->filter()->join('/') }}{{ $empresa->telefone ? ' · '.$empresa->telefone : '' }}</div>
        </div>
    </header>

    <main class="mx-auto max-w-2xl px-4 py-6">
        @include('partials.flash')

        <div class="mb-5 flex items-center gap-4">
            <x-placa :placa="$os->veiculo->placa" tamanho="md" />
            <div class="min-w-0">
                <h1 class="text-xl">{{ $os->veiculo->descricao() ?: 'Seu veículo' }}</h1>
                <p class="text-suave">Orçamento nº {{ $os->numeroFormatado() }} · {{ $os->created_at->format('d/m/Y') }}@if($os->token_expira_em) · válido até {{ $os->token_expira_em->format('d/m/Y') }}@endif</p>
            </div>
        </div>

        @if ($pendentes->isNotEmpty())
            <section class="card card-corpo mb-4 space-y-2">
                <h2>{{ $complementar ? 'Orçamento complementar' : 'Itens para aprovação' }}</h2>
                <p>{{ $nome ? 'Olá, '.$nome.'! ' : '' }}Marque os itens que você quer autorizar. Os desmarcados ficam de fora desta aprovação.</p>
                @if ($os->previsao_entrega)<p class="text-sm">Previsão atual de entrega <strong>{{ $os->previsao_entrega->format('d/m/Y H:i') }}</strong></p>@endif
                @if ($complementar)
                    <details class="text-sm">
                        <summary class="cursor-pointer font-semibold">Serviços e peças já aprovados · {{ Dinheiro::formatar($aprovados->sum('total_centavos')) }}</summary>
                        <ul class="mt-2 list-disc pl-5">@foreach ($aprovados as $item)<li>{{ $item->descricao }} · {{ Dinheiro::formatar($item->total_centavos) }}</li>@endforeach</ul>
                    </details>
                @endif
            </section>
        @elseif ($os->status === StatusOs::Entregue)
            <p class="mb-4 text-[15px]">Serviço concluído e veículo entregue em {{ $os->entregue_em->format('d/m/Y') }}.@if($os->garantiaAte()) Garantia até {{ $os->garantiaAte()->format('d/m/Y') }}.@endif</p>
        @elseif ($aprovados->isNotEmpty())
            <p class="mb-4 text-[15px]">Situação: <strong>{{ $os->status->rotulo() }}</strong>. Não há nada esperando sua aprovação.</p>
        @endif

        @if ($os->diagnostico)
            <section class="card card-corpo mb-4">
                <h2 class="mb-1 text-base">O que o mecânico encontrou</h2>
                <p class="whitespace-pre-line text-[15px]">{{ $os->diagnostico }}</p>
            </section>
        @endif

        @if ($inspecoesGerais->isNotEmpty())
            <section class="card card-corpo mb-4 space-y-2">
                <h2>Inspeção visual do veículo</h2>
                @include('os._inspecoes', ['publico' => true, 'registros' => $inspecoesGerais])
            </section>
        @endif

        @if ($fotosGerais->isNotEmpty())
            <div class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
                @foreach ($fotosGerais as $f)
                    <a href="{{ route('orcamento.foto', [$os->token, $f->id]) }}" target="_blank" class="block overflow-hidden rounded-md border border-linha bg-white">
                        <img src="{{ route('orcamento.foto', [$os->token, $f->id]) }}" alt="{{ $f->legenda ?: 'Foto do veículo' }}" loading="lazy" class="aspect-[4/3] w-full object-cover">
                        @if ($f->legenda)<span class="block truncate px-2 py-1 text-xs text-suave">{{ $f->legenda }}</span>@endif
                    </a>
                @endforeach
            </div>
        @endif

        @if ($pendentes->isNotEmpty())
            <form method="post" action="{{ route('orcamento.aprovar', $os->token) }}" class="card mb-24" data-orcamento>
                @csrf
                <input type="hidden" name="versao" value="{{ $os->versao }}">
                <ul class="divide-y divide-linha">
                    @foreach ($pendentes as $item)
                        <li>
                            <label class="flex cursor-pointer items-start gap-3 px-4 py-3.5">
                                <input type="checkbox" name="itens[]" value="{{ $item->id }}" data-valor="{{ $item->total_centavos }}" class="mt-0.5 h-6 w-6 shrink-0 accent-asfalto" @checked(! old() || in_array($item->id, old('itens', [])))>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-medium">{{ $item->descricao }}</span>
                                    <span class="block text-sm text-suave">{{ $item->tipo === 'peca' ? 'Peça' : 'Serviço' }}{{ (float) $item->quantidade != 1 ? ' · '.$item->quantidadeFormatada().' × '.Dinheiro::formatar($item->valor_unitario_centavos) : '' }}</span>
                                </span>
                                <span class="whitespace-nowrap font-semibold">{{ Dinheiro::formatar($item->total_centavos) }}</span>
                            </label>
                            @if ($inspecoesPorItem->has($item->id))
                                <div class="px-4 pb-4 sm:pl-13">
                                    @include('os._inspecoes', ['publico' => true, 'registros' => $inspecoesPorItem->get($item->id), 'dentroDoItem' => true])
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <div class="border-t border-linha bg-[#fafbfc] px-4 py-4">
                    @php $jaAprovado = $aprovados->sum('total_centavos'); @endphp
                    @if ($jaAprovado)
                        <div class="flex justify-between text-[15px] text-suave"><span>Já aprovado antes</span><span>{{ Dinheiro::formatar($jaAprovado) }}</span></div>
                    @endif
                    @if ($os->desconto_centavos)
                        <div class="flex justify-between text-[15px] text-suave"><span>Desconto</span><span>− {{ Dinheiro::formatar($os->desconto_centavos) }}</span></div>
                    @endif
                    @error('itens')<p class="erro-msg">{{ $message }}</p>@enderror

                    <label class="campo mt-4 scroll-mb-28" id="confirmar-orcamento"><span>Seu nome, para confirmar</span>
                        <input name="nome" value="{{ old('nome') }}" class="input @error('nome') input-erro @enderror" required maxlength="120" autocomplete="name">
                    </label>
                    @error('nome')<p class="erro-msg">{{ $message }}</p>@enderror

                    <div class="mt-4 grid gap-2 sm:grid-cols-[2fr_1fr]">
                        <button name="decisao" value="aprovar" class="btn-primario py-3 text-base">Aprovar itens marcados</button>
                        <button name="decisao" value="recusar" class="btn py-3" formnovalidate data-confirmar="Recusar todo o orçamento?">Recusar orçamento</button>
                    </div>
                </div>
                <div class="fixed inset-x-0 bottom-0 z-20 border-t border-linha bg-white shadow-lg pb-[env(safe-area-inset-bottom)]">
                    <div class="mx-auto flex max-w-2xl items-center justify-between gap-2 px-4 py-3">
                        <div>
                            <span class="block text-xs text-suave">Total com a seleção</span>
                            <strong aria-live="polite" data-orcamento-total data-base="{{ $jaAprovado - $os->desconto_centavos }}">{{ Dinheiro::formatar($jaAprovado + $pendentes->sum('total_centavos') - $os->desconto_centavos) }}</strong>
                            @if ($complementar)<span class="block text-xs text-suave">Adicionais selecionados <span data-orcamento-adicionais>{{ Dinheiro::formatar($pendentes->sum('total_centavos')) }}</span></span>@endif
                        </div>
                        <a href="#confirmar-orcamento" class="btn-sinal">Revisar e confirmar</a>
                    </div>
                </div>
            </form>
        @endif

        @if ($aprovados->isNotEmpty() && $pendentes->isEmpty())
            <section class="card">
                <ul class="divide-y divide-linha">
                    @foreach ($aprovados as $item)
                        <li class="flex justify-between gap-3 px-4 py-3"><span>{{ $item->descricao }}</span><span class="whitespace-nowrap">{{ Dinheiro::formatar($item->total_centavos) }}</span></li>
                    @endforeach
                </ul>
                <div class="flex justify-between border-t border-linha px-4 py-3 text-lg font-semibold"><span>Total</span><span>{{ $os->totalFormatado() }}</span></div>
            </section>
        @endif

        @if ($recusados->isNotEmpty())
            <p class="mt-4 text-sm text-suave">Não autorizados: {{ $recusados->pluck('descricao')->join(', ') }}. Se mudar de ideia, fale com a oficina.</p>
        @endif

        <p class="mt-8 text-center text-xs text-suave">Aprovação registrada com data, hora e nome. Dúvidas? Fale com a {{ $empresa->nome_fantasia }}{{ $empresa->telefone ? ' pelo '.$empresa->telefone : '' }}.</p>
    </main>
</body>
</html>
