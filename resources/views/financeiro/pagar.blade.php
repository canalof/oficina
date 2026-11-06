@php use App\Support\Dinheiro; use App\Services\Financeiro\Financeiro; use App\Models\ContaPagar; @endphp
<x-app titulo="Contas a pagar">
    <div class="mb-5">
        <h1>Contas a pagar</h1>
        <p class="text-suave">Em aberto: <strong>{{ Dinheiro::formatar($totalAberto) }}</strong> · próximos 7 dias: <strong>{{ Dinheiro::formatar($totalSemana) }}</strong></p>
    </div>
    @include('financeiro._abas', ['abas' => ['abertas' => 'Em aberto', 'vencidas' => 'Vencidas', 'pagas' => 'Pagas']])

    <div class="card overflow-x-auto">
        <table class="tabela">
            <thead><tr><th>Vencimento</th><th>Descrição</th><th class="hidden sm:table-cell">Categoria</th><th class="text-right">Valor</th><th></th></tr></thead>
            <tbody>
            @forelse ($contas as $c)
                <tr>
                    <td @class(['whitespace-nowrap', 'font-semibold text-erro' => $c->vencida()])>{{ $c->vencimento->format('d/m/Y') }}@if($c->pago_em)<div class="text-xs text-ok">pago {{ $c->pago_em->format('d/m') }}</div>@endif</td>
                    <td><div class="font-medium">{{ $c->descricao }}</div>@if($c->fornecedor)<div class="text-sm text-suave">{{ $c->fornecedor->nome }}{{ $c->documento ? ' · doc. '.$c->documento : '' }}</div>@endif</td>
                    <td class="hidden text-sm sm:table-cell">{{ $c->categoria }}</td>
                    <td class="whitespace-nowrap text-right font-semibold">{{ Dinheiro::formatar($c->valor_centavos) }}</td>
                    <td class="text-right">
                        @if ($c->status === 'aberta')
                            @can('financeiro.editar')
                                <form method="post" action="{{ route('pagar.pagar', $c) }}" class="flex justify-end gap-1">
                                    @csrf
                                    <select name="forma" class="input w-auto py-1 text-sm" aria-label="Forma">
                                        @foreach (['pix', 'boleto', 'transferencia', 'dinheiro', 'debito', 'credito'] as $f)<option value="{{ $f }}">{{ Financeiro::FORMAS[$f] }}</option>@endforeach
                                    </select>
                                    <button class="btn btn-sm">Paguei</button>
                                </form>
                            @endcan
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-10 text-center text-suave">Nada por aqui.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $contas->links() }}
    </div>

    @can('financeiro.editar')
        <section class="card card-corpo mt-6 max-w-3xl">
            <h2 class="mb-3">Lançar despesa</h2>
            <form method="post" action="{{ route('pagar.store') }}" class="grid gap-3 sm:grid-cols-6">
                @csrf
                <x-campo nome="descricao" rotulo="Descrição" required placeholder="Ex.: Aluguel do galpão" class="sm:col-span-3" />
                <label class="campo sm:col-span-3"><span>Categoria</span><select name="categoria" class="input">@foreach(ContaPagar::CATEGORIAS as $cat)<option>{{ $cat }}</option>@endforeach</select></label>
                <label class="campo sm:col-span-3"><span>Fornecedor</span><select name="fornecedor_id" class="input"><option value="">—</option>@foreach($fornecedores as $f)<option value="{{ $f->id }}">{{ $f->nome }}</option>@endforeach</select></label>
                <x-campo nome="valor" rotulo="Valor (R$)" mascara="dinheiro" inputmode="decimal" required class="sm:col-span-1" />
                <x-campo nome="vencimento" rotulo="Vencimento" tipo="date" required class="sm:col-span-2" />
                <x-campo nome="documento" rotulo="Nº do documento" class="sm:col-span-2" />
                <x-campo nome="repetir" rotulo="Repetir por quantos meses" tipo="number" min="1" max="24" valor="1" ajuda="Para contas fixas (aluguel, internet)" class="sm:col-span-2" />
                <div class="flex items-end sm:col-span-2"><button class="btn-primario">Lançar</button></div>
            </form>
        </section>
    @endcan
</x-app>
