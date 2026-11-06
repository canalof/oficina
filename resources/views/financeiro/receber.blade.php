@php use App\Support\Dinheiro; use App\Services\Financeiro\Financeiro; @endphp
<x-app titulo="Contas a receber">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1>Contas a receber</h1>
            <p class="text-suave">Em aberto: <strong>{{ Dinheiro::formatar($totalAberto) }}</strong>@if($totalVencido) · <span class="font-semibold text-erro">vencido: {{ Dinheiro::formatar($totalVencido) }}</span>@endif</p>
        </div>
    </div>
    @include('financeiro._abas', ['abas' => ['abertas' => 'Em aberto', 'vencidas' => 'Vencidas', 'recebidas' => 'Recebidas']])

    <div class="card overflow-x-auto">
        <table class="tabela">
            <thead><tr><th>Vencimento</th><th>Cliente / descrição</th><th class="text-right">Valor</th><th></th></tr></thead>
            <tbody>
            @forelse ($contas as $c)
                <tr>
                    <td @class(['whitespace-nowrap', 'font-semibold text-erro' => $c->vencida()])>{{ $c->vencimento->format('d/m/Y') }}@if($c->recebido_em)<div class="text-xs text-ok">recebido {{ $c->recebido_em->format('d/m') }}</div>@endif</td>
                    <td>
                        <div class="font-medium">{{ $c->cliente?->nome ?? 'Sem cliente' }}</div>
                        <div class="text-sm text-suave">{{ $c->descricao }}{{ $c->parcela ? ' · '.$c->parcela : '' }} · {{ Financeiro::FORMAS[$c->forma_recebida ?? $c->forma] ?? '' }}</div>
                    </td>
                    <td class="whitespace-nowrap text-right font-semibold">{{ Dinheiro::formatar($c->valor_centavos) }}</td>
                    <td class="text-right">
                        @if ($c->status === 'aberta')
                            <div class="flex flex-wrap justify-end gap-2">
                                @can('caixa.operar')
                                    <form method="post" action="{{ route('receber.receber', $c) }}" class="flex gap-1">
                                        @csrf
                                        <select name="forma" class="input w-auto py-1 text-sm" aria-label="Forma">
                                            @foreach (Financeiro::IMEDIATAS as $f)<option value="{{ $f }}">{{ Financeiro::FORMAS[$f] }}</option>@endforeach
                                        </select>
                                        <button class="btn btn-sm">Recebi</button>
                                    </form>
                                @endcan
                                @if ($c->cliente && ($l = $c->cliente->linkWhatsapp('Olá, '.explode(' ', $c->cliente->nome)[0].'! Passando para lembrar do pagamento de '.Dinheiro::formatar($c->valor_centavos).' com vencimento em '.$c->vencimento->format('d/m/Y').'. '.auth()->user()->empresa->nome_fantasia)))
                                    <a href="{{ $l }}" target="_blank" rel="noopener" class="btn btn-sm">Cobrar</a>
                                @endif
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-10 text-center text-suave">Nada por aqui.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $contas->links() }}
    </div>

    @can('financeiro.editar')
        <details class="card card-corpo mt-6 max-w-2xl">
            <summary class="cursor-pointer font-semibold">Lançar conta a receber avulsa</summary>
            <form method="post" action="{{ route('receber.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                @csrf
                <label class="campo"><span>Cliente</span><select name="cliente_id" class="input"><option value="">—</option>@foreach($clientes as $cl)<option value="{{ $cl->id }}">{{ $cl->nome }}</option>@endforeach</select></label>
                <x-campo nome="descricao" rotulo="Descrição" required />
                <x-campo nome="valor" rotulo="Valor (R$)" mascara="dinheiro" inputmode="decimal" required />
                <x-campo nome="vencimento" rotulo="Vencimento" tipo="date" required />
                <div><button class="btn-primario">Lançar</button></div>
            </form>
        </details>
    @endcan
</x-app>
