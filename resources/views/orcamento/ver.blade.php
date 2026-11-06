@php
    use App\Support\Dinheiro;
    use App\Support\StatusOs;
    $pendentes = $os->itens->where('situacao', 'pendente');
    $aprovados = $os->itens->where('situacao', 'aprovado');
    $recusados = $os->itens->where('situacao', 'recusado');
    $nome = $os->cliente ? explode(' ', trim($os->cliente->nome))[0] : null;
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
                <p class="text-suave">Orçamento nº {{ $os->numeroFormatado() }} · {{ $os->created_at->format('d/m/Y') }}</p>
            </div>
        </div>

        @if ($pendentes->isNotEmpty())
            <p class="mb-4 text-[15px]">{{ $nome ? 'Olá, '.$nome.'! ' : '' }}Confira o que encontramos e marque o que você autoriza. Os itens desmarcados não serão feitos.</p>
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

        @if ($os->fotos->isNotEmpty())
            <div class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
                @foreach ($os->fotos as $f)
                    <a href="{{ route('orcamento.foto', [$os->token, $f->id]) }}" target="_blank" class="block overflow-hidden rounded-md border border-linha bg-white">
                        <img src="{{ route('orcamento.foto', [$os->token, $f->id]) }}" alt="{{ $f->legenda ?: 'Foto do veículo' }}" loading="lazy" class="aspect-[4/3] w-full object-cover">
                        @if ($f->legenda)<span class="block truncate px-2 py-1 text-xs text-suave">{{ $f->legenda }}</span>@endif
                    </a>
                @endforeach
            </div>
        @endif

        @if ($pendentes->isNotEmpty())
            <form method="post" action="{{ route('orcamento.aprovar', $os->token) }}" class="card" data-orcamento>
                @csrf
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
                    <div class="flex items-baseline justify-between text-lg font-semibold">
                        <span>Total</span>
                        <span data-orcamento-total data-base="{{ $jaAprovado - $os->desconto_centavos }}">{{ Dinheiro::formatar($jaAprovado + $pendentes->sum('total_centavos') - $os->desconto_centavos) }}</span>
                    </div>
                    @error('itens')<p class="erro-msg">{{ $message }}</p>@enderror

                    <label class="campo mt-4"><span>Seu nome, para confirmar</span>
                        <input name="nome" value="{{ old('nome', $os->cliente?->nome) }}" class="input @error('nome') input-erro @enderror" required maxlength="120" autocomplete="name">
                    </label>
                    @error('nome')<p class="erro-msg">{{ $message }}</p>@enderror

                    <div class="mt-4 grid gap-2 sm:grid-cols-[2fr_1fr]">
                        <button name="decisao" value="aprovar" class="btn-primario py-3 text-base">Aprovar itens marcados</button>
                        <button name="decisao" value="recusar" class="btn py-3" formnovalidate data-confirmar="Recusar todo o orçamento?">Recusar orçamento</button>
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
