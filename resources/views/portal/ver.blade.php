@php use App\Support\Dinheiro; @endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer">
    <title>Meus carros · {{ $empresa->nome_fantasia }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen">
    <header class="bg-asfalto text-white"><div class="mx-auto max-w-2xl px-4 py-4"><div class="text-lg font-bold">{{ $empresa->nome_fantasia }}</div><div class="text-sm text-[#c5ced6]">{{ $empresa->telefone }}</div></div></header>
    <main class="mx-auto max-w-2xl px-4 py-6">
        <h1 class="mb-1 text-xl">Olá, {{ explode(' ', trim($cliente->nome))[0] }}!</h1>
        <p class="mb-6 text-suave">Aqui ficam os serviços feitos nos seus carros, as garantias e as próximas revisões.</p>

        @if ($lembretes->isNotEmpty())
            <section class="card card-corpo mb-5">
                <h2 class="mb-2 text-base">Próximas revisões</h2>
                <ul class="space-y-1 text-[15px]">@foreach($lembretes as $l)<li class="flex justify-between gap-3"><span>{{ $l->descricao }}</span><span class="text-suave">{{ $l->data_prevista->format('d/m/Y') }}{{ $l->km_previsto ? ' ou '.number_format($l->km_previsto, 0, ',', '.').' km' : '' }}</span></li>@endforeach</ul>
            </section>
        @endif

        @foreach ($cliente->veiculos as $v)
            <section class="card mb-5">
                <div class="flex items-center gap-4 border-b border-linha px-4 py-3"><x-placa :placa="$v->placa" /><span class="font-semibold">{{ $v->descricao() }}</span></div>
                @forelse ($v->ordens as $o)
                    <div class="border-b border-linha px-4 py-3 last:border-b-0">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-medium">{{ $o->created_at->format('d/m/Y') }} · OS {{ $o->numeroFormatado() }}</span>
                            <span class="rotulo {{ $o->status->cor() }}">{{ $o->status->rotulo() }}</span>
                        </div>
                        <p class="mt-1 text-sm">{{ $o->itens->where('situacao', 'aprovado')->pluck('descricao')->join(', ') ?: 'Em orçamento' }}</p>
                        <div class="mt-1 flex flex-wrap justify-between gap-2 text-sm text-suave">
                            <span>{{ Dinheiro::formatar($o->total_centavos) }}</span>
                            @if ($o->garantiaAte())<span @class(['text-ok' => $o->garantiaAte()->isFuture()])>Garantia até {{ $o->garantiaAte()->format('d/m/Y') }}</span>@endif
                        </div>
                        @if ($o->itens->where('situacao', 'pendente')->isNotEmpty() && $o->linkValido())
                            <a href="{{ route('orcamento.ver', $o->token) }}" class="btn-primario btn-sm mt-2">Ver e aprovar orçamento</a>
                        @endif
                    </div>
                @empty
                    <p class="px-4 py-4 text-sm text-suave">Nenhum serviço ainda.</p>
                @endforelse
            </section>
        @endforeach
    </main>
</body>
</html>
