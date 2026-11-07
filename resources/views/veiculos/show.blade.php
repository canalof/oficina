<x-app :titulo="'Veículo '.$veiculo->placaFormatada()">
    <div class="mb-5 flex flex-wrap items-center gap-4">
        <x-placa :placa="$veiculo->placa" tamanho="md" />
        <div class="min-w-0 flex-1">
            <h1>{{ $veiculo->descricao() ?: 'Modelo não informado' }}</h1>
            <p class="text-suave">{{ collect([$veiculo->cor, $veiculo->combustivel])->filter()->join(' · ') }}</p>
        </div>
        @can('veiculos.editar')<a href="{{ route('veiculos.edit', $veiculo) }}" class="btn">Editar</a>@endcan
    </div>

    <div class="grid gap-6 lg:grid-cols-[1.3fr_1fr]">
        <section class="card card-corpo">
            <h2 class="mb-4">Dados do veículo</h2>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-[15px] sm:grid-cols-3">
                <div><dt class="text-sm text-suave">Km atual</dt><dd class="font-semibold">{{ $veiculo->km_atual ? number_format($veiculo->km_atual, 0, ',', '.') : '—' }}</dd></div>
                <div><dt class="text-sm text-suave">Ano</dt><dd class="font-semibold">{{ $veiculo->ano_fabricacao ? $veiculo->ano_fabricacao.'/'.$veiculo->ano_modelo : '—' }}</dd></div>
                <div><dt class="text-sm text-suave">Valor FIPE</dt><dd class="font-semibold">{{ $veiculo->fipe_valor_centavos ? 'R$ '.number_format($veiculo->fipe_valor_centavos / 100, 2, ',', '.') : '—' }}</dd></div>
                <div><dt class="text-sm text-suave">Código FIPE</dt><dd>{{ $veiculo->fipe_codigo ?: '—' }}</dd></div>
                <div class="col-span-2"><dt class="text-sm text-suave">Chassi</dt><dd class="break-all">{{ $veiculo->chassi ?: '—' }}</dd></div>
            </dl>
            @if ($veiculo->observacoes)<p class="mt-4 whitespace-pre-line border-t border-linha pt-4 text-[15px]">{{ $veiculo->observacoes }}</p>@endif
        </section>

        <section class="card card-corpo">
            <h2 class="mb-4">Dono</h2>
            @if ($veiculo->cliente)
                <a href="{{ route('clientes.show', $veiculo->cliente) }}" class="text-lg font-semibold hover:underline">{{ $veiculo->cliente->nome }}</a>
                <p class="text-sm text-suave">{{ $veiculo->cliente->whatsapp ?: $veiculo->cliente->telefone }}</p>
            @else
                <p class="text-suave">Sem dono cadastrado.</p>
            @endif

            @can('veiculos.editar')
                <form method="post" action="{{ route('veiculos.transferir', $veiculo) }}" id="transferir" class="mt-5 border-t border-linha pt-4">
                    @csrf
                    <label class="campo"><span>{{ $veiculo->cliente ? 'Transferir para outro dono' : 'Vincular a um cliente' }}</span>
                        <select name="cliente_id" class="input" required>
                            <option value="">Escolha o cliente…</option>
                            @foreach ($clientes as $c)
                                @continue($c->id === $veiculo->cliente_id)
                                <option value="{{ $c->id }}">{{ $c->nome }}</option>
                            @endforeach
                        </select>
                    </label>
                    @error('cliente_id')<p class="erro-msg">{{ $message }}</p>@enderror
                    <button class="btn btn-sm mt-3">{{ $veiculo->cliente ? 'Transferir' : 'Vincular' }}</button>
                </form>
            @endcan

            @if ($veiculo->donos->count())
                <h3 class="mt-6 text-sm font-semibold text-suave">Histórico de donos</h3>
                <ol class="mt-2 space-y-1 text-[15px]">
                    @foreach ($veiculo->donos as $d)
                        <li class="flex justify-between gap-3">
                            <span>{{ $d->cliente?->nome ?? 'Cliente excluído' }}</span>
                            <span class="whitespace-nowrap text-suave">{{ $d->inicio->format('d/m/Y') }} – {{ $d->fim ? $d->fim->format('d/m/Y') : 'atual' }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>

    <section class="card card-corpo mt-6 text-[15px] text-suave">
        Ordens de serviço, itens recusados, garantias e lembretes de revisão deste carro aparecem aqui no próximo módulo.
    </section>

    @can('veiculos.excluir')
        <form method="post" action="{{ route('veiculos.destroy', $veiculo) }}" class="mt-8" data-confirmar="Excluir este veículo?">
            @csrf @method('delete')
            <button class="btn-perigo btn-sm">Excluir veículo</button>
        </form>
    @endcan
</x-app>
