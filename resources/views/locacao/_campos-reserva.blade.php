<label class="campo"><span>Condutor</span><select class="input" name="condutor_id" required>
    <option value="">Selecione quem vai dirigir</option>
    @foreach ($condutores as $condutor)<option value="{{ $condutor->id }}" @selected((int) old('condutor_id') === $condutor->id)>{{ $condutor->nome }}{{ $condutor->categoria ? ' · CNH '.$condutor->categoria : '' }}</option>@endforeach
</select></label>
@if ($condutores->isEmpty())<p class="text-sm text-suave">Nenhum condutor cadastrado. Cadastre em <a class="underline" href="{{ route('mobilidade.index') }}#condutores">Condutores</a>.</p>@endif
@if ($permiteParceira)
    <label class="campo"><span>Carro</span><select class="input" name="tipo">
        @foreach (\App\Models\MobilidadeReserva::ORIGENS as $valor => $rotulo)<option value="{{ $valor }}" @selected(old('tipo', 'frota') === $valor)>{{ $rotulo }}</option>@endforeach
    </select></label>
@endif
<label class="campo"><span>Carro da frota{{ $permiteParceira ? ' (deixe em branco se for de locadora parceira)' : '' }}</span><select class="input" name="frota_id" @unless ($permiteParceira) required @endunless>
    <option value="">Selecione</option>
    @foreach ($disponiveis as $carro)<option value="{{ $carro->id }}" @selected((int) old('frota_id') === $carro->id)>{{ \App\Support\Placa::formatar($carro->placa) }} · {{ $carro->descricao }}</option>@endforeach
</select></label>
@if ($permiteParceira)
    <div class="grid gap-3 sm:grid-cols-2">
        <x-campo nome="parceira" rotulo="Locadora parceira" maxlength="120" />
        <x-campo nome="contrato_externo" rotulo="Nº do contrato na parceira" maxlength="120" />
    </div>
@endif
<div class="grid gap-3 sm:grid-cols-2">
    <x-campo nome="inicio" rotulo="Saída" tipo="datetime-local" required />
    <x-campo nome="fim" rotulo="Devolução prevista" tipo="datetime-local" required />
    <x-campo nome="diaria" rotulo="Diária (R$)" mascara="dinheiro" inputmode="decimal" :ajuda="$permiteParceira ? 'Deixe em branco se o carro reserva for cortesia.' : null" />
    <x-campo nome="caucao" rotulo="Caução (R$)" mascara="dinheiro" inputmode="decimal" />
</div>
