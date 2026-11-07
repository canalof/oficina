@php use App\Support\Dinheiro; @endphp
<x-app titulo="Notas de compra">
    <h1 class="mb-1">Notas de compra</h1>
    <p class="mb-5 text-suave">Importe o XML que o fornecedor manda por e-mail: o sistema cadastra o fornecedor, reconhece os produtos e lança as parcelas em contas a pagar.</p>

    @can('compras.lancar')
        <form method="post" action="{{ route('compras.store') }}" enctype="multipart/form-data" class="card card-corpo mb-6 flex flex-wrap items-end gap-3">
            @csrf
            <label class="campo min-w-[240px] flex-1"><span>Arquivo XML da nota</span>
                <input type="file" name="xml" accept=".xml,text/xml,application/xml" required class="input py-1.5 text-sm file:mr-3 file:rounded file:border-0 file:bg-asfalto file:px-3 file:py-1 file:text-white">
            </label>
            <button class="btn-primario">Ler nota</button>
            @error('xml')<p class="erro-msg w-full">{{ $message }}</p>@enderror
        </form>
    @endcan

    <div class="card overflow-x-auto">
        <table class="tabela">
            <thead><tr><th>Nota</th><th>Fornecedor</th><th class="hidden sm:table-cell">Emissão</th><th class="hidden md:table-cell text-right">Itens</th><th class="text-right">Total</th><th>Situação</th></tr></thead>
            <tbody>
            @forelse ($compras as $c)
                <tr>
                    <td><a href="{{ route('compras.show', $c) }}" class="font-semibold hover:underline">{{ $c->numero ?: '#'.$c->id }}</a></td>
                    <td>{{ $c->fornecedor?->nome ?? '—' }}</td>
                    <td class="hidden sm:table-cell">{{ $c->emissao?->format('d/m/Y') }}</td>
                    <td class="hidden md:table-cell text-right">{{ $c->itens_count }}</td>
                    <td class="whitespace-nowrap text-right">{{ Dinheiro::formatar($c->total_centavos) }}</td>
                    <td>@if($c->lancada())<span class="rotulo border-[#b7dcc6] bg-[#eef8f2] text-ok">No estoque</span>@else<span class="rotulo border-[#f0d58a] bg-[#fff7df] text-sinal-escuro">Conferir</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-10 text-center text-suave">Nenhuma nota importada ainda.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $compras->links() }}
    </div>
</x-app>
