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
        @include('partials.flash')

        @if ($cliente->veiculos->isNotEmpty())
            <section class="card card-corpo mb-5 space-y-3">
                <h2>Solicitar agendamento</h2>
                <p class="text-sm text-suave">Informe sua preferência. A oficina vai responder por aqui.</p>
                <form method="post" action="{{ route('portal.solicitar', request()->route('token')) }}" class="space-y-3">
                    @csrf
                    <label class="campo"><span>Veículo</span><select class="input" name="veiculo_id" required>
                        @foreach ($cliente->veiculos as $veiculo)<option value="{{ $veiculo->id }}">{{ $veiculo->placaFormatada() }} · {{ $veiculo->descricao() }}</option>@endforeach
                    </select></label>
                    <x-campo nome="preferencia" rotulo="Dia e hora de preferência" tipo="datetime-local" required />
                    <x-campo nome="servico" rotulo="Serviço ou problema percebido" maxlength="200" required />
                    @error('veiculo_id')<p class="erro-msg">{{ $message }}</p>@enderror
                    <button class="btn-primario">Enviar solicitação</button>
                </form>
                @foreach ($solicitacoes as $pedido)
                    <div class="border-t border-linha pt-2 text-sm">
                        {{ $pedido->veiculo?->placaFormatada() }} · {{ $pedido->servico }} ·
                        @if ($pedido->status === 'pendente')
                            Aguardando resposta para {{ $pedido->preferencia->format('d/m/Y H:i') }}
                        @elseif ($pedido->status === 'confirmada' && $pedido->agendamento && ! in_array($pedido->agendamento->status, ['cancelado', 'faltou'], true))
                            Confirmado para {{ $pedido->agendamento->inicio->format('d/m/Y H:i') }}
                        @elseif ($pedido->status === 'recusada')
                            Solicitação recusada
                        @elseif ($pedido->status === 'proposta' && $pedido->agendamento?->status === 'proposto' && $pedido->agendamento->inicio->isFuture())
                            Horário proposto {{ $pedido->agendamento->inicio->format('d/m/Y H:i') }}
                            <form class="mt-2 flex gap-2" method="post" action="{{ route('portal.proposta.responder', [request()->route('token'), $pedido]) }}">
                                @csrf
                                <button class="btn-primario btn-sm" name="decisao" value="aceitar">Aceitar horário</button>
                                <button class="btn btn-sm" name="decisao" value="recusar">Recusar horário</button>
                            </form>
                        @else
                            Agendamento encerrado
                        @endif
                    </div>
                @endforeach
            </section>
        @endif

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
                        @if ($o->laudo)
                            <details class="mt-3 rounded border border-linha p-3">
                                <summary class="cursor-pointer font-medium">Laudo de alinhamento e pneus</summary>
                                @include('os._laudo-medicoes', ['laudo' => $o->laudo])
                            </details>
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
