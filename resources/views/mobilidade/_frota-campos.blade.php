<div class="grid gap-3 sm:grid-cols-2">
    <x-campo nome="placa" rotulo="Placa" :valor="$carro?->placa" mascara="placa" required maxlength="8" />
    <x-campo nome="descricao" rotulo="Modelo e identificação" :valor="$carro?->descricao" required maxlength="120" />
    <x-campo nome="km" rotulo="Quilometragem" tipo="number" :valor="$carro?->km ?? 0" min="0" required />
    <label class="campo"><span>Situação</span><select class="input" name="situacao" required>
        @foreach (['disponivel' => 'Disponível para reservas', 'manutencao' => 'Em manutenção', 'inativo' => 'Inativo'] as $valor => $rotulo)
            <option value="{{ $valor }}" @selected($carro?->situacao === $valor)>{{ $rotulo }}</option>
        @endforeach
    </select></label>
</div>
<label class="campo"><span>Registro de manutenção</span><textarea class="input" name="manutencao" maxlength="4000" rows="2">{{ $carro?->manutencao }}</textarea></label>
