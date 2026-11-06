<section class="card">
    <div class="flex flex-wrap items-center gap-4 border-b border-linha px-5 py-4">
        <x-placa :placa="$placa" tamanho="md" />
        <div class="min-w-0 flex-1">
            <div class="text-lg font-semibold">{{ $dados ? trim($dados->marca.' '.$dados->modelo) : 'Placa nova' }}</div>
            <div class="text-sm text-suave">
                @if ($dados)
                    {{ collect([$dados->versao, $dados->anoFabricacao && $dados->anoModelo ? $dados->anoFabricacao.'/'.$dados->anoModelo : null, $dados->cor, $dados->combustivel])->filter()->join(' · ') }}
                @else
                    Este carro ainda não está cadastrado.
                @endif
            </div>
        </div>
        <span class="rotulo border-[#f0d58a] bg-[#fff7df] text-sinal-escuro">Primeira visita</span>
    </div>

    @if ($aviso)
        <p class="border-b border-linha bg-[#fff7df] px-5 py-3 text-[15px] text-[#7a5600]" role="alert">{{ $aviso }}</p>
    @endif

    @if ($dados)
        <dl class="grid grid-cols-2 gap-x-6 gap-y-4 px-5 py-5 sm:grid-cols-4">
            <div><dt class="text-sm text-suave">Marca</dt><dd class="font-semibold">{{ $dados->marca ?? '—' }}</dd></div>
            <div><dt class="text-sm text-suave">Modelo</dt><dd class="font-semibold">{{ $dados->modelo ?? '—' }}</dd></div>
            <div><dt class="text-sm text-suave">Ano</dt><dd class="font-semibold">{{ $dados->anoFabricacao ? $dados->anoFabricacao.'/'.$dados->anoModelo : '—' }}</dd></div>
            <div><dt class="text-sm text-suave">FIPE</dt><dd class="font-semibold">{{ $dados->fipeValorCentavos ? 'R$ '.number_format($dados->fipeValorCentavos / 100, 0, ',', '.') : '—' }}</dd></div>
        </dl>
        <p class="border-t border-linha px-5 py-3 text-sm text-suave">Dados da consulta de placa. A consulta não traz informações do proprietário.</p>
    @endif

    @can('veiculos.editar')
        <div class="flex flex-wrap gap-3 border-t border-linha px-5 py-4">
            @can('clientes.editar')
                <a href="{{ route('clientes.create', ['placa' => $placa]) }}" class="btn-primario">Cadastrar cliente novo e o carro</a>
            @endcan
            <a href="{{ route('veiculos.create', ['placa' => $placa]) }}" class="btn">O dono já é cliente</a>
        </div>
    @endcan
</section>
