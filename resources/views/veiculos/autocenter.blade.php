@extends('layouts.app')
@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div><h1 class="text-2xl font-bold">Autocenter — {{ $veiculo->placaFormatada() }}</h1><a href="{{ route('veiculos.show', $veiculo) }}" class="underline">Voltar ao veículo</a> · <a href="{{ route('veiculos.historico-manutencao', $veiculo) }}" class="underline">Histórico consolidado</a></div>
    @if(session('ok'))<div role="status" class="p-3 bg-green-100 text-green-900">{{ session('ok') }}</div>@endif
    @if($errors->any())<div role="alert" class="p-3 bg-red-100 text-red-900"><ul>@foreach($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>@endif
    @can('veiculos.editar')
    <form method="POST" action="{{ route('veiculos.autocenter.store', $veiculo) }}" class="p-4 border rounded space-y-3">@csrf
        <h2 class="font-semibold">Registrar serviço especializado</h2>
        <label>Serviço <select name="tipo" required>@foreach($tipos as $tipo)<option value="{{ $tipo }}" @selected(old('tipo') === $tipo)>{{ ucfirst(str_replace('_', ' ', $tipo)) }}</option>@endforeach</select></label>
        <label>OS vinculada (opcional) <select name="ordem_servico_id"><option value="">Sem vínculo</option>@foreach($ordens as $os)<option value="{{ $os->id }}" @selected(old('ordem_servico_id') == $os->id)>OS #{{ $os->numero }}</option>@endforeach</select></label>
        <label>KM <input type="number" min="0" max="9999999" name="km_registrado" value="{{ old('km_registrado', $veiculo->km_atual) }}"></label>
        <p class="text-sm">Para rodízio, informe as duas posições distintas. Registre cada troca separadamente.</p>
        <label>Origem <select name="posicao_origem"><option value="">—</option>@foreach($posicoes as $posicao)<option value="{{ $posicao }}" @selected(old('posicao_origem') === $posicao)>{{ str_replace('_', ' ', $posicao) }}</option>@endforeach</select></label>
        <label>Destino <select name="posicao_destino"><option value="">—</option>@foreach($posicoes as $posicao)<option value="{{ $posicao }}" @selected(old('posicao_destino') === $posicao)>{{ str_replace('_', ' ', $posicao) }}</option>@endforeach</select></label>
        <label>Observações <textarea name="observacoes" maxlength="2000">{{ old('observacoes') }}</textarea></label>
        <button class="btn btn-primary" type="submit">Registrar serviço</button>
    </form>
    @endcan
    <h2 class="text-xl font-semibold">Histórico de serviços</h2>
    <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr><th>Data</th><th>Serviço</th><th>OS</th><th>KM</th><th>Movimentação</th><th>Observações</th></tr></thead><tbody>
    @forelse($registros as $r)<tr><td>{{ $r->created_at->format('d/m/Y H:i') }}</td><td>{{ str_replace('_', ' ', $r->tipo) }}</td><td>{{ $r->ordem_servico_id ?? '—' }}</td><td>{{ $r->km_registrado ?? '—' }}</td><td>{{ $r->posicao_origem ? str_replace('_', ' ', $r->posicao_origem).' → '.str_replace('_', ' ', $r->posicao_destino) : '—' }}</td><td>{{ $r->observacoes }}</td></tr>
    @empty<tr><td colspan="6">Nenhum serviço registrado.</td></tr>@endforelse
    </tbody></table></div>{{ $registros->links() }}
</div>
@endsection
