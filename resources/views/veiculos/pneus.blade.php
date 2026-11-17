@extends('layouts.app')
@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div><h1 class="text-2xl font-bold">Inspeção de pneus — {{ $veiculo->placaFormatada() }}</h1>
        <a href="{{ route('veiculos.show', $veiculo) }}" class="underline">Voltar ao veículo</a> · <a href="{{ route('veiculos.historico-manutencao', $veiculo) }}" class="underline">Histórico consolidado</a></div>
    @if(session('ok'))<div role="status" class="p-3 bg-green-100 text-green-900">{{ session('ok') }}</div>@endif
    @if($errors->any())<div role="alert" class="p-3 bg-red-100 text-red-900"><ul>@foreach($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>@endif
    @can('veiculos.editar')
    <form method="POST" action="{{ route('veiculos.pneus.store', $veiculo) }}" class="p-4 border rounded space-y-3">@csrf
        <h2 class="font-semibold">Nova inspeção (histórico permanente)</h2>
        <label>Posição <select name="posicao" required>@foreach($posicoes as $posicao)<option value="{{ $posicao }}" @selected(old('posicao') === $posicao)>{{ str_replace('_', ' ', ucfirst($posicao)) }}</option>@endforeach</select></label>
        <label>Medida <input name="medida" maxlength="30" value="{{ old('medida') }}" placeholder="205/55 R16"></label>
        <label>Marca <input name="marca" maxlength="80" value="{{ old('marca') }}"></label>
        <label>DOT (SSAA) <input name="dot" maxlength="4" pattern="[0-9]{4}" value="{{ old('dot') }}" placeholder="2524"></label>
        <label>Sulco (décimos de mm) <input type="number" name="sulco_decimos_mm" min="0" max="300" value="{{ old('sulco_decimos_mm') }}" placeholder="65 = 6,5 mm"></label>
        <label>Quilometragem <input type="number" name="km_registrado" min="0" value="{{ old('km_registrado', $veiculo->km_atual) }}"></label>
        <label>Observações <textarea name="observacoes" maxlength="2000">{{ old('observacoes') }}</textarea></label>
        <button type="submit" class="btn btn-primary">Registrar inspeção</button>
    </form>
    @endcan
    <h2 class="text-xl font-semibold">Histórico</h2>
    <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr><th>Data</th><th>Posição</th><th>Medida / Marca</th><th>DOT</th><th>Sulco</th><th>KM</th></tr></thead><tbody>
    @forelse($inspecoes as $i)<tr><td>{{ $i->created_at->format('d/m/Y H:i') }}</td><td>{{ str_replace('_', ' ', $i->posicao) }}</td><td>{{ $i->medida }} {{ $i->marca }}</td><td>{{ $i->dot ?? '—' }}</td><td>{{ $i->sulco_decimos_mm === null ? '—' : number_format($i->sulco_decimos_mm / 10, 1, ',', '.') . ' mm' }}</td><td>{{ $i->km_registrado ?? '—' }}</td></tr>
    @empty<tr><td colspan="6">Nenhuma inspeção registrada.</td></tr>@endforelse
    </tbody></table></div>{{ $inspecoes->links() }}
</div>
@endsection
