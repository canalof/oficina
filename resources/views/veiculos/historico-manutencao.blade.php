@extends('layouts.app')
@section('content')
<div class="max-w-6xl mx-auto space-y-5">
    <h1 class="text-2xl font-bold">Histórico de manutenção — {{ $veiculo->placaFormatada() }}</h1>
    <nav class="flex gap-4 flex-wrap">
        <a class="underline" href="{{ route('veiculos.show', $veiculo) }}">Voltar ao veículo</a>
        <a class="underline" href="{{ route('veiculos.autocenter.index', $veiculo) }}">Serviços de autocenter</a>
        <a class="underline" href="{{ route('veiculos.pneus.index', $veiculo) }}">Inspeções de pneus</a>
        <a class="underline" href="{{ route('veiculos.historico-manutencao.csv', $veiculo) }}">Exportar CSV</a>
    </nav>
    <p class="text-sm">Últimos registros de OS, autocenter e pneus (até 100 de cada categoria, exibidos até 150 no total). Histórico de consulta, sem movimentação financeira ou de estoque.</p>
    <div class="overflow-x-auto"><table class="w-full text-sm">
        <thead><tr><th>Data</th><th>Origem</th><th>Descrição</th><th>OS</th><th>KM</th><th>Observações</th></tr></thead>
        <tbody>@forelse($historico as $evento)
            <tr><td>{{ $evento['data']?->format('d/m/Y H:i') ?? '—' }}</td>
                <td>{{ $evento['tipo'] }}</td><td>{{ $evento['descricao'] }}</td>
                <td>{{ $evento['os'] ?? '—' }}</td><td>{{ $evento['km'] ?? '—' }}</td>
                <td>{{ $evento['observacoes'] ?: '—' }}</td></tr>
        @empty<tr><td colspan="6">Nenhuma manutenção registrada.</td></tr>@endforelse</tbody>
    </table></div>
</div>
@endsection
