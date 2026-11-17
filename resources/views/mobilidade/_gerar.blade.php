@can($permissao ?? 'mobilidade.editar')
    @if ($documentosDisponiveis)
        <form method="post" action="{{ route('mobilidade.documento.gerar') }}" class="my-3 flex flex-wrap items-end gap-2">
            @csrf
            <input type="hidden" name="{{ $vinculo }}" value="{{ $registro->id }}">
            <label class="campo"><span>Documento</span><select class="input" name="tipo" required>
                @foreach ($documentosDisponiveis as $tipo)<option value="{{ $tipo }}">{{ \App\Http\Controllers\MobilidadeDocumentoController::TIPOS[$tipo] }}</option>@endforeach
            </select></label>
            <button class="btn btn-sm">Gerar documento</button>
        </form>
    @endif
@endcan
