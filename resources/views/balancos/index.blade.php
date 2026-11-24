@php use App\Support\Dinheiro; @endphp
<x-app titulo="Balanço de estoque">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1>Balanço de estoque</h1>
            <p class="text-suave">Conte vários produtos de uma vez, pela tela, pelo leitor de código de barras ou por planilha.</p>
        </div>
        @can('estoque.editar')<a href="{{ route('balancos.create') }}" class="btn-primario">Novo balanço</a>@endcan
    </div>

    <div class="card overflow-x-auto">
        <table class="tabela">
            <thead><tr><th>Nº</th><th>Data</th><th class="hidden sm:table-cell">Quem fez</th><th class="text-right">Contados</th><th class="text-right">Ajustados</th><th class="text-right">Diferença (custo)</th></tr></thead>
            <tbody>
            @forelse ($balancos as $b)
                <tr>
                    <td><a href="{{ route('balancos.show', $b) }}" class="font-semibold hover:underline">{{ $b->id }}</a></td>
                    <td class="whitespace-nowrap">{{ $b->created_at->format('d/m/Y H:i') }}@if ($b->observacao)<div class="text-xs text-suave">{{ $b->observacao }}</div>@endif</td>
                    <td class="hidden sm:table-cell">{{ $b->user?->name ?? '—' }}</td>
                    <td class="text-right">{{ $b->produtos_contados }}</td>
                    <td class="text-right">{{ $b->produtos_ajustados }}</td>
                    <td @class(['whitespace-nowrap text-right font-semibold', 'text-erro' => $b->diferenca_valor_centavos < 0])>{{ $b->diferenca_valor_centavos ? Dinheiro::formatar($b->diferenca_valor_centavos) : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-10 text-center text-suave">Nenhum balanço ainda.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $balancos->links() }}
    </div>
</x-app>
