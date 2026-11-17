<x-app titulo="Extrato mensal da frota">
    <h1>Extrato mensal da frota</h1>
    <a class="underline" href="{{ route('clientes.show', $cliente) }}">{{ $cliente->nome }}</a>
    <p class="my-3 text-suave">Resumo das ordens entregues no mês. Os valores representam serviços e peças da OS, independentemente do pagamento.</p>
    <form method="get" class="mb-4 flex flex-wrap items-end gap-2">
        <x-campo nome="mes" rotulo="Mês das entregas" tipo="month" :valor="$mes" required />
        <button class="btn">Filtrar</button>
    </form>
    <div class="card overflow-x-auto">
        <table class="tabela">
            <thead><tr><th>OS</th><th>Placa</th><th>Entregue em</th><th>Valor</th></tr></thead>
            <tbody>
                @forelse ($ordens as $os)
                    <tr>
                        <td><a class="underline" href="{{ route('os.show', $os) }}">{{ $os->numeroFormatado() }}</a></td>
                        <td>{{ $os->veiculo?->placaFormatada() ?? '—' }}</td>
                        <td>{{ $os->entregue_em->format('d/m/Y') }}</td>
                        <td>{{ \App\Support\Dinheiro::formatar($os->total_centavos) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-6 text-center">Nenhuma OS entregue no período.</td></tr>
                @endforelse
            </tbody>
            <tfoot><tr><th colspan="3">Total do mês</th><th>{{ \App\Support\Dinheiro::formatar($total) }}</th></tr></tfoot>
        </table>
        {{ $ordens->links() }}
    </div>
</x-app>
