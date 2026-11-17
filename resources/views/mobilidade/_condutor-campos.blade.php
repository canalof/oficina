<div class="grid gap-3 sm:grid-cols-2">
    <x-campo nome="nome" rotulo="Nome completo" :valor="$condutor?->nome" required maxlength="120" />
    <label class="campo"><span>CPF (opcional)</span><input name="cpf" class="input" inputmode="numeric" maxlength="11" value="{{ $condutor?->cpf }}" autocomplete="off"></label>
    <label class="campo"><span>Registro da CNH</span><input name="registro_cnh" class="input" inputmode="numeric" maxlength="11" value="{{ $condutor?->registro_cnh }}" autocomplete="off"></label>
    <label class="campo"><span>Contato</span><input name="contato" class="input" maxlength="120" value="{{ $condutor?->contato }}" autocomplete="off"></label>
    <label class="campo"><span>Categoria informada</span>
        <select class="input" name="categoria"><option value="">Não informada</option>
            @foreach (['ACC','A','B','C','D','E','AB','AC','AD','AE'] as $cat)
                <option value="{{ $cat }}" @selected(old('categoria', $condutor?->categoria) === $cat)>{{ $cat }}</option>
            @endforeach
        </select>
    </label>
    <x-campo nome="validade" rotulo="Validade informada" tipo="date" :valor="$condutor?->validade?->toDateString()" />
</div>
