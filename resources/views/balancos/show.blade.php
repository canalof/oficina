@php use App\Support\Dinheiro; use App\Models\Produto; @endphp
<x-app :titulo="'Balanço nº '.$balanco->id">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1>Balanço nº {{ $balanco->id }}</h1>
            <p class="text-suave">{{ $balanco->created_at->format('d/m/Y H:i') }} · {{ $balanco->user?->name ?? 'Usuário removido' }}@if ($balanco->observacao) · {{ $balanco->observacao }}@endif</p>
        </div>
        <div class="flex gap-2">
            <button type="button" class="btn" onclick="window.print()">Imprimir</button>
            <a href="{{ route('balancos.index') }}" class="btn">Voltar</a>
        </div>
    </div>

    <div class="mb-5 grid gap-3 sm:grid-cols-3">
        <div class="card card-corpo"><p class="text-sm text-suave">Produtos contados</p><p class="text-2xl font-semibold">{{ $balanco->produtos_contados }}</p></div>
        <div class="card card-corpo"><p class="text-sm text-suave">Ajustados</p><p class="text-2xl font-semibold">{{ $balanco->produtos_ajustados }}</p></div>
        <div class="card card-corpo"><p class="text-sm text-suave">Diferença pelo custo</p><p @class(['text-2xl font-semibold', 'text-erro' => $balanco->diferenca_valor_centavos < 0])>{{ Dinheiro::formatar($balanco->diferenca_valor_centavos) }}</p></div>
    </div>

    @if ($itens->contains(fn ($i) => $i->mudouDurante()))
        <p class="mb-4 rounded-md border border-[#f0d58a] bg-[#fff7df] px-4 py-3 text-[15px]">Alguns produtos tiveram venda ou entrada entre a contagem e a aplicação (marcados com *). Confira se a contagem já considerava esse movimento.</p>
    @endif

    <div class="card overflow-x-auto">
        <table class="tabela">
            <thead><tr><th>Produto</th><th class="text-right">No sistema</th><th class="text-right">Contado</th><th class="text-right">Diferença</th><th class="hidden text-right sm:table-cell">Valor (custo)</th></tr></thead>
            <tbody>
            @foreach ($itens as $i)
                @php $dif = (float) $i->diferenca; @endphp
                <tr @class(['text-suave' => $dif == 0])>
                    <td>@if ($i->produto)<a href="{{ route('produtos.show', $i->produto) }}" class="font-semibold hover:underline">{{ $i->produto->nome }}</a>@else Produto removido @endif @if ($i->mudouDurante())<span title="Saldo mudou depois que a lista foi gerada (era {{ Produto::formatarQtd($i->saldo_visto) }})">*</span>@endif</td>
                    <td class="whitespace-nowrap text-right">{{ Produto::formatarQtd($i->saldo_sistema) }}</td>
                    <td class="whitespace-nowrap text-right">{{ Produto::formatarQtd($i->contado) }}</td>
                    <td @class(['whitespace-nowrap text-right font-semibold', 'text-erro' => $dif < 0, 'text-ok' => $dif > 0])>{{ $dif == 0 ? 'Bateu' : ($dif > 0 ? '+' : '−').Produto::formatarQtd(abs($dif)) }}</td>
                    <td class="hidden whitespace-nowrap text-right sm:table-cell">{{ $dif == 0 ? '—' : Dinheiro::formatar((int) round($dif * $i->custo_unitario_centavos)) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-app>
