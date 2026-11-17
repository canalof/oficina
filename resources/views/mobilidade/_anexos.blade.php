@if ($fotos->isNotEmpty())
    <div class="my-2 flex flex-wrap gap-2">
        @foreach ($fotos as $foto)<a class="btn btn-sm" href="{{ route('mobilidade.foto', $foto) }}" target="_blank" rel="noopener">Foto {{ $foto->etapa }} #{{ $foto->id }}</a>@endforeach
    </div>
@endif
@can('mobilidade.editar')
    @foreach ($etapas as $etapa)
        <form method="post" enctype="multipart/form-data" action="{{ route('mobilidade.foto.store') }}" class="my-3 flex flex-wrap items-end gap-2">
            @csrf
            <input type="hidden" name="{{ $vinculo }}" value="{{ $registro->id }}">
            <input type="hidden" name="etapa" value="{{ $etapa }}">
            <label class="campo"><span>Foto do veículo — {{ $etapa }}</span><input class="input" type="file" name="foto" accept="image/jpeg,image/png" required></label>
            <button class="btn btn-sm">Anexar foto</button>
        </form>
    @endforeach
@endcan
