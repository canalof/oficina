@php $c = $veiculo->cliente; @endphp
<div class="grid gap-6 lg:grid-cols-[1.2fr_1fr] [&>*]:min-w-0">
    <section class="card">
        <div class="flex flex-wrap items-center gap-4 border-b border-linha px-5 py-4">
            <x-placa :placa="$veiculo->placa" tamanho="md" />
            <div class="min-w-0 flex-1">
                <div class="text-lg font-semibold">{{ $veiculo->descricao() ?: 'Modelo não informado' }}</div>
                <div class="text-sm text-suave">{{ collect([$veiculo->cor, $veiculo->combustivel])->filter()->join(' · ') }}</div>
            </div>
            <span class="rotulo border-[#b7dcc6] bg-[#eef8f2] text-ok">Já é cliente</span>
        </div>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-4 px-5 py-5 sm:grid-cols-3">
            <div><dt class="text-sm text-suave">Último km registrado</dt><dd class="text-xl font-semibold">{{ $veiculo->km_atual ? number_format($veiculo->km_atual, 0, ',', '.') : '—' }}</dd></div>
            <div><dt class="text-sm text-suave">Cadastrado em</dt><dd class="text-xl font-semibold">{{ $veiculo->created_at->format('d/m/Y') }}</dd></div>
            <div><dt class="text-sm text-suave">Valor FIPE</dt><dd class="text-xl font-semibold">{{ $veiculo->fipe_valor_centavos ? 'R$ '.number_format($veiculo->fipe_valor_centavos / 100, 0, ',', '.') : '—' }}</dd></div>
        </dl>
        @php $ordens = $veiculo->ordens()->with('itens')->limit(3)->get(); @endphp
        @php $aberta = $ordens->first(fn ($o) => $o->status->noPatio()); @endphp
        @if ($ordens->isNotEmpty())
            <div class="border-t border-linha">
                <div class="px-5 pt-3 text-sm font-semibold text-suave">Últimas visitas</div>
                @include('os._historico', ['ordens' => $ordens])
            </div>
        @endif
        <div class="flex flex-wrap gap-3 border-t border-linha px-5 py-4">
            @if ($aberta)
                <a href="{{ route('os.show', $aberta) }}" class="btn-sinal">Continuar OS {{ $aberta->numeroFormatado() }}</a>
            @else
                @can('os.editar')<a href="{{ route('os.create', ['veiculo_id' => $veiculo->id]) }}" class="btn-sinal">Abrir ordem de serviço</a>@endcan
            @endif
            <a href="{{ route('veiculos.show', $veiculo) }}" class="btn">Ficha do veículo</a>
            @can('veiculos.editar')<a href="{{ route('veiculos.edit', $veiculo) }}" class="btn">Atualizar km e dados</a>@endcan
        </div>
    </section>

    <section class="card">
        <div class="border-b border-linha px-5 py-4"><h2>Cliente</h2></div>
        @if ($c)
            <div class="px-5 py-5">
                <div class="text-lg font-semibold">{{ $c->nome }}</div>
                <div class="text-sm text-suave">{{ $c->documentoFormatado() ?: 'Sem CPF/CNPJ' }}</div>
                <dl class="mt-4 space-y-2 text-[15px]">
                    <div class="flex justify-between gap-4"><dt class="text-suave">Telefone</dt><dd>{{ $c->whatsapp ?: $c->telefone ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-suave">E-mail</dt><dd class="truncate">{{ $c->email ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-suave">Outros carros</dt><dd>{{ $c->veiculos()->where('id', '<>', $veiculo->id)->count() }}</dd></div>
                </dl>
                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="{{ route('clientes.show', $c) }}" class="btn">Ver cliente</a>
                    @if ($c->aceita_whatsapp && ($link = $c->linkWhatsapp('Olá, '.explode(' ', $c->nome)[0].'! Aqui é da '.auth()->user()->empresa->nome_fantasia.'.')))
                        <a href="{{ $link }}" target="_blank" rel="noopener" class="btn">WhatsApp</a>
                    @endif
                </div>
            </div>
        @else
            <div class="px-5 py-6 text-[15px] text-suave">
                Este veículo está sem dono cadastrado.
                @can('veiculos.editar')<a class="font-semibold text-texto underline" href="{{ route('veiculos.show', $veiculo) }}#transferir">Vincular a um cliente</a>@endcan
            </div>
        @endif
    </section>
</div>
