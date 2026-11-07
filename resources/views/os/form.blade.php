@php use App\Support\Checklist; $editando = $os->exists; $ck = old('checklist', $os->checklist ?? []); @endphp
<x-app :titulo="$editando ? 'Editar OS '.$os->numeroFormatado() : 'Abrir OS'">
    <div class="mb-5 flex flex-wrap items-center gap-4">
        <x-placa :placa="$veiculo->placa" tamanho="md" />
        <div class="min-w-0 flex-1">
            <h1>{{ $editando ? 'Editar OS '.$os->numeroFormatado() : 'Abrir ordem de serviço' }}</h1>
            <p class="text-suave">{{ $veiculo->descricao() ?: 'Modelo não informado' }}{{ $veiculo->cliente ? ' · '.$veiculo->cliente->nome : '' }}</p>
        </div>
    </div>

    @if ($aberta)
        <div class="mb-5 rounded-md border border-[#f0d58a] bg-[#fff7df] px-4 py-3 text-[15px]">
            Este carro já tem a <a href="{{ route('os.show', $aberta) }}" class="font-semibold underline">OS {{ $aberta->numeroFormatado() }}</a> aberta ({{ $aberta->status->rotulo() }}).
            Se for o mesmo atendimento, inclua os itens nela.
        </div>
    @endif

    <form method="post" data-avisar-saida action="{{ $editando ? route('os.update', $os) : route('os.store') }}" class="space-y-6">
        @csrf
        @if ($editando) @method('put') @else <input type="hidden" name="veiculo_id" value="{{ $veiculo->id }}"> @endif

        <section class="card card-corpo">
            <h2 class="mb-4">Entrada do carro</h2>
            <label class="campo mb-4 max-w-md"><span>Cliente</span>
                <select name="cliente_id" class="input">
                    <option value="">Sem cliente</option>
                    @foreach ($clientes as $c)
                        <option value="{{ $c->id }}" @selected((int) old('cliente_id', $os->cliente_id) === $c->id)>{{ $c->nome }}{{ $c->id === $veiculo->cliente_id ? ' (dono do carro)' : '' }}</option>
                    @endforeach
                </select>
            </label>
            @error('cliente_id')<p class="erro-msg">{{ $message }}</p>@enderror
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-campo nome="km_entrada" rotulo="Km no painel" :valor="$os->km_entrada ? number_format($os->km_entrada, 0, ',', '.') : ''" mascara="km" inputmode="numeric" :ajuda="$veiculo->km_atual ? 'Último registrado: '.number_format($veiculo->km_atual, 0, ',', '.') : null" />
                <x-campo nome="previsao_entrega" rotulo="Previsão de entrega" tipo="datetime-local" :valor="$os->previsao_entrega?->format('Y-m-d\TH:i')" />
                <label class="campo"><span>Mecânico responsável</span>
                    <select name="mecanico_id" class="input">
                        <option value="">Definir depois</option>
                        @foreach ($mecanicos as $m)
                            <option value="{{ $m->id }}" @selected((int) old('mecanico_id', $os->mecanico_id) === $m->id)>{{ $m->name }}</option>
                        @endforeach
                    </select>
                </label>
                <x-campo nome="garantia_dias" rotulo="Garantia (dias)" tipo="number" min="0" :valor="$os->garantia_dias ?? 90" />
            </div>

            <fieldset class="mt-5">
                <legend class="mb-1.5 text-sm font-medium text-suave">Combustível</legend>
                <div class="inline-flex overflow-hidden rounded-md border border-[#c9cfd6]">
                    @foreach (Checklist::COMBUSTIVEL as $valor => $rotulo)
                        <label class="cursor-pointer border-r border-[#c9cfd6] last:border-r-0">
                            <input type="radio" name="combustivel" value="{{ $valor }}" class="peer sr-only" @checked((string) old('combustivel', $os->combustivel) === (string) $valor)>
                            <span class="block px-3.5 py-2 text-[15px] font-medium peer-checked:bg-asfalto peer-checked:text-white peer-focus-visible:outline-3 peer-focus-visible:outline-sinal">{{ $rotulo }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <label class="campo mt-5"><span>O que o cliente relatou</span>
                <textarea name="defeito_relatado" rows="3" class="input" placeholder="Ex.: barulho na roda dianteira ao frear, luz da injeção acesa">{{ old('defeito_relatado', $os->defeito_relatado) }}</textarea>
            </label>
            @error('defeito_relatado')<p class="erro-msg">{{ $message }}</p>@enderror
        </section>

        <section class="card">
            <div class="border-b border-linha px-5 py-4">
                <h2>Checklist de entrada</h2>
                <p class="text-sm text-suave">Confira com o cliente antes de ele sair. Fotos podem ser anexadas depois, na própria OS.</p>
            </div>
            <div class="divide-y divide-linha">
                @foreach (Checklist::ITENS as $chave => $rotulo)
                    @php $atual = $ck[$chave]['estado'] ?? null; @endphp
                    <div class="grid items-center gap-2 px-5 py-2.5 sm:grid-cols-[1fr_auto_1fr] sm:gap-4">
                        <span class="text-[15px] font-medium">{{ $rotulo }}</span>
                        <div class="inline-flex w-fit overflow-hidden rounded-md border border-[#c9cfd6] text-sm" role="radiogroup" aria-label="{{ $rotulo }}">
                            @foreach (Checklist::ESTADOS as $estado => $rotuloEstado)
                                <label class="cursor-pointer border-r border-[#c9cfd6] last:border-r-0">
                                    <input type="radio" name="checklist[{{ $chave }}][estado]" value="{{ $estado }}" class="peer sr-only" @checked($atual === $estado)>
                                    <span @class([
                                        'block px-3 py-1.5 font-medium peer-focus-visible:outline-3 peer-focus-visible:outline-sinal',
                                        'peer-checked:bg-ok peer-checked:text-white' => $estado === 'ok',
                                        'peer-checked:bg-erro peer-checked:text-white' => $estado === 'avaria',
                                        'peer-checked:bg-asfalto-3 peer-checked:text-white' => $estado === 'nao_tem',
                                    ])>{{ $rotuloEstado }}</span>
                                </label>
                            @endforeach
                        </div>
                        <input name="checklist[{{ $chave }}][obs]" value="{{ $ck[$chave]['obs'] ?? '' }}" class="input py-1.5 text-sm" placeholder="Observação (ex.: risco na porta)" aria-label="Observação sobre {{ $rotulo }}">
                    </div>
                @endforeach
            </div>
        </section>

        <section class="card card-corpo">
            <label class="campo"><span>Observações internas</span>
                <textarea name="observacoes" rows="2" class="input" placeholder="Não aparece para o cliente">{{ old('observacoes', $os->observacoes) }}</textarea>
            </label>
        </section>

        <div class="flex flex-wrap gap-3">
            <button class="btn-primario">{{ $editando ? 'Salvar alterações' : 'Abrir OS' }}</button>
            <a href="{{ $editando ? route('os.show', $os) : route('veiculos.show', $veiculo) }}" class="btn">Voltar</a>
        </div>
    </form>
</x-app>
