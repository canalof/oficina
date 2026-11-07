<x-app :titulo="$veiculo->exists ? 'Editar veículo' : 'Novo veículo'">
    <h1 class="mb-1">{{ $veiculo->exists ? 'Editar veículo' : 'Novo veículo' }}</h1>
    <p class="mb-5 text-suave">
        @if (! $veiculo->exists && $veiculo->marca) Dados preenchidos pela consulta da placa. Confira antes de salvar.
        @else Placa e modelo são obrigatórios. @endif
    </p>

    <form method="post" data-avisar-saida action="{{ $veiculo->exists ? route('veiculos.update', $veiculo) : route('veiculos.store') }}" class="space-y-5">
        @csrf
        @if ($veiculo->exists) @method('put') @endif
        <input type="hidden" name="fipe_codigo" value="{{ old('fipe_codigo', $veiculo->fipe_codigo) }}">
        <input type="hidden" name="fipe_valor_centavos" value="{{ old('fipe_valor_centavos', $veiculo->fipe_valor_centavos) }}">

        <section class="card card-corpo">
            <div class="grid gap-4 md:grid-cols-6">
                <x-campo nome="placa" rotulo="Placa" :valor="$veiculo->placa" mascara="placa" required class="md:col-span-2" autocapitalize="characters" />
                <label class="campo md:col-span-4">
                    <span>Dono</span>
                    <select name="cliente_id" class="input @error('cliente_id') input-erro @enderror">
                        <option value="">Sem dono cadastrado</option>
                        @foreach ($clientes as $c)
                            <option value="{{ $c->id }}" @selected((string) old('cliente_id', $veiculo->cliente_id) === (string) $c->id)>{{ $c->nome }}{{ $c->cpf_cnpj ? ' · '.\App\Support\Documento::formatar($c->cpf_cnpj) : '' }}</option>
                        @endforeach
                    </select>
                    @error('cliente_id')<p class="erro-msg">{{ $message }}</p>@enderror
                </label>
                <x-campo nome="marca" rotulo="Marca" :valor="$veiculo->marca" class="md:col-span-2" />
                <x-campo nome="modelo" rotulo="Modelo" :valor="$veiculo->modelo" required class="md:col-span-2" />
                <x-campo nome="versao" rotulo="Versão" :valor="$veiculo->versao" class="md:col-span-2" />
                <x-campo nome="motor" rotulo="Motor" :valor="$veiculo->motor" placeholder="Ex.: 1.0 12V Flex" class="md:col-span-2" />
                <x-campo nome="ano_fabricacao" rotulo="Ano de fabricação" tipo="number" :valor="$veiculo->ano_fabricacao" inputmode="numeric" class="md:col-span-1" />
                <x-campo nome="ano_modelo" rotulo="Ano do modelo" tipo="number" :valor="$veiculo->ano_modelo" inputmode="numeric" class="md:col-span-1" />
                <x-campo nome="cor" rotulo="Cor" :valor="$veiculo->cor" class="md:col-span-1" />
                <x-campo nome="combustivel" rotulo="Combustível" :valor="$veiculo->combustivel" list="combustiveis" class="md:col-span-1" />
                <x-campo nome="km_atual" rotulo="Km atual" :valor="$veiculo->km_atual ? number_format($veiculo->km_atual, 0, ',', '.') : null" mascara="km" inputmode="numeric" class="md:col-span-2" />
                <x-campo nome="chassi" rotulo="Chassi" :valor="$veiculo->chassi" maxlength="17" ajuda="Opcional, 17 caracteres" class="md:col-span-3" />
                <x-campo nome="renavam" rotulo="Renavam" :valor="$veiculo->renavam" inputmode="numeric" maxlength="11" ajuda="Opcional, 11 números" class="md:col-span-3" />
                <label class="campo md:col-span-6"><span>Observações</span><textarea name="observacoes" rows="2" class="input">{{ old('observacoes', $veiculo->observacoes) }}</textarea></label>
            </div>
            <datalist id="combustiveis"><option>Flex</option><option>Gasolina</option><option>Etanol</option><option>Diesel</option><option>Híbrido</option><option>Elétrico</option><option>GNV</option></datalist>
        </section>

        <div class="flex gap-3">
            <button class="btn-primario px-6">Salvar veículo</button>
            <a href="{{ $veiculo->exists ? route('veiculos.show', $veiculo) : route('veiculos.index') }}" class="btn">Cancelar</a>
        </div>
    </form>
</x-app>
