@php
    $modelosPorMarca = [
        'Chevrolet' => ['Agile', 'Celta', 'Classic', 'Cobalt', 'Corsa', 'Cruze', 'Montana', 'Onix', 'Prisma', 'S10', 'Spin', 'Tracker'],
        'Fiat' => ['Argo', 'Cronos', 'Fastback', 'Fiorino', 'Mobi', 'Palio', 'Pulse', 'Siena', 'Strada', 'Toro', 'Uno'],
        'Ford' => ['EcoSport', 'Fiesta', 'Focus', 'Ka', 'Ranger'],
        'Honda' => ['City', 'Civic', 'Fit', 'HR-V', 'WR-V'],
        'Hyundai' => ['Creta', 'HB20', 'HB20S', 'Tucson'],
        'Jeep' => ['Commander', 'Compass', 'Renegade'],
        'Nissan' => ['Frontier', 'Kicks', 'March', 'Sentra', 'Versa'],
        'Peugeot' => ['206', '207', '208', '2008', 'Partner'],
        'Renault' => ['Clio', 'Duster', 'Kardian', 'Kwid', 'Logan', 'Oroch', 'Sandero'],
        'Toyota' => ['Corolla', 'Corolla Cross', 'Etios', 'Hilux', 'SW4', 'Yaris'],
        'Volkswagen' => ['Fox', 'Gol', 'Golf', 'Nivus', 'Polo', 'Saveiro', 'T-Cross', 'Taos', 'Virtus', 'Voyage', 'up!'],
    ];
@endphp
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
            <h2 class="mb-3">Identificação do veículo</h2>
            <p class="mb-4 text-sm text-suave">Escolha uma sugestão ou digite os dados conforme o documento do carro. Confira a versão, o ano e o motor antes de salvar.</p>
            <div class="mb-4 rounded-md border border-linha bg-fundo p-3" data-selecao-veiculo hidden>
                <p class="mb-2 font-semibold">Preencher marca e modelo</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="campo"><span>Selecionar marca</span>
                        <select class="input" data-marca-sugerida>
                            <option value="">Escolha uma marca</option>
                            @foreach (array_keys($modelosPorMarca) as $marca)<option value="{{ $marca }}">{{ $marca }}</option>@endforeach
                        </select>
                    </label>
                    <label class="campo"><span>Selecionar modelo</span>
                        <select class="input" data-modelo-sugerido disabled><option value="">Escolha a marca primeiro</option></select>
                    </label>
                </div>
                <p class="mt-2 text-sm text-suave">Se o carro não estiver na lista, preencha os campos Marca e Modelo abaixo.</p>
            </div>
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
                <x-campo nome="marca" rotulo="Marca" :valor="$veiculo->marca" list="marcas-veiculo" class="md:col-span-2" />
                <x-campo nome="modelo" rotulo="Modelo" :valor="$veiculo->modelo" list="modelos-veiculo" required class="md:col-span-2" />
                <x-campo nome="versao" rotulo="Versão" :valor="$veiculo->versao" class="md:col-span-2" />
                <x-campo nome="motor" rotulo="Motor" :valor="$veiculo->motor" list="motores-veiculo" placeholder="Ex.: 1.0 12V Flex" class="md:col-span-2" />
                <x-campo nome="ano_fabricacao" rotulo="Ano de fabricação" tipo="number" :valor="$veiculo->ano_fabricacao" inputmode="numeric" min="1950" :max="now()->year + 1" list="anos-fabricacao" class="md:col-span-1" />
                <x-campo nome="ano_modelo" rotulo="Ano do modelo" tipo="number" :valor="$veiculo->ano_modelo" inputmode="numeric" min="1950" :max="now()->year + 2" list="anos-modelo" class="md:col-span-1" />
                <x-campo nome="cor" rotulo="Cor" :valor="$veiculo->cor" class="md:col-span-1" />
                <x-campo nome="combustivel" rotulo="Combustível" :valor="$veiculo->combustivel" list="combustiveis" class="md:col-span-1" />
                <x-campo nome="km_atual" rotulo="Km atual" :valor="$veiculo->km_atual ? number_format($veiculo->km_atual, 0, ',', '.') : null" mascara="km" inputmode="numeric" class="md:col-span-2" />
                <x-campo nome="chassi" rotulo="Chassi" :valor="$veiculo->chassi" maxlength="17" ajuda="Opcional, 17 caracteres" class="md:col-span-3" />
                <x-campo nome="renavam" rotulo="Renavam" :valor="$veiculo->renavam" inputmode="numeric" maxlength="11" ajuda="Opcional, 11 números" class="md:col-span-3" />
                <label class="campo md:col-span-6"><span>Observações</span><textarea name="observacoes" rows="2" class="input">{{ old('observacoes', $veiculo->observacoes) }}</textarea></label>
            </div>
            <datalist id="marcas-veiculo">
                @foreach (array_keys($modelosPorMarca) as $marca)<option value="{{ $marca }}">@endforeach
            </datalist>
            <datalist id="modelos-veiculo" data-modelos-veiculo="{{ json_encode($modelosPorMarca) }}">
                @foreach (collect($modelosPorMarca)->flatten()->unique()->sort() as $modelo)<option value="{{ $modelo }}">@endforeach
            </datalist>
            <datalist id="motores-veiculo">
                @foreach (['1.0', '1.0 Turbo', '1.2', '1.3', '1.4', '1.4 Turbo', '1.5', '1.5 Turbo', '1.6', '1.8', '2.0', '2.0 Turbo', '2.2 Diesel', '2.8 Diesel'] as $motor)<option value="{{ $motor }}">@endforeach
            </datalist>
            <datalist id="anos-fabricacao">
                @foreach (range(now()->year + 1, 1980) as $ano)<option value="{{ $ano }}">@endforeach
            </datalist>
            <datalist id="anos-modelo">
                @foreach (range(now()->year + 2, 1980) as $ano)<option value="{{ $ano }}">@endforeach
            </datalist>
            <datalist id="combustiveis"><option>Flex</option><option>Gasolina</option><option>Etanol</option><option>Diesel</option><option>Híbrido</option><option>Elétrico</option><option>GNV</option></datalist>
        </section>

        <div class="flex gap-3">
            <button class="btn-primario px-6">Salvar veículo</button>
            <a href="{{ $veiculo->exists ? route('veiculos.show', $veiculo) : route('veiculos.index') }}" class="btn">Cancelar</a>
        </div>
    </form>
</x-app>
