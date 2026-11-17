<x-app titulo="Lembretes de revisão">
    <h1 class="mb-1">Lembretes de revisão</h1>
    <p class="mb-5 text-suave">Criados sozinhos na entrega do carro, para os serviços do catálogo que têm prazo de revisão (ex.: troca de óleo a cada 6 meses).
        {{ $automatico ? 'O WhatsApp automático envia os próximos 7 dias todo dia às 9h.' : 'Abra o WhatsApp com a mensagem pronta e confirme o envio depois de enviá-la.' }}</p>
    @include('financeiro._abas', ['abas' => ['proximos' => 'Próximos 30 dias', 'todos' => 'Todos', 'enviados' => 'Enviados'], 'filtro' => $ver])
    <div class="card overflow-x-auto">
        <table class="tabela">
            <thead><tr><th>Quando</th><th>Carro</th><th>Cliente</th><th>Revisão</th><th></th></tr></thead>
            <tbody>
            @forelse ($lembretes as $l)
                <tr>
                    <td @class(['whitespace-nowrap', 'font-semibold text-erro' => $l->status === 'pendente' && $l->data_prevista->isPast()])>{{ $l->data_prevista->format('d/m/Y') }}@if($l->km_previsto)<div class="text-xs text-suave">ou {{ number_format($l->km_previsto, 0, ',', '.') }} km</div>@endif</td>
                    <td><a href="{{ route('veiculos.show', $l->veiculo_id) }}"><x-placa :placa="$l->veiculo->placa" /></a><div class="text-xs text-suave">{{ $l->veiculo->descricao() }}</div></td>
                    <td>{{ $l->cliente?->nome ?? '—' }}</td>
                    <td>{{ $l->descricao }}@if($l->enviado_em)<div class="text-xs text-ok">enviado {{ $l->enviado_em->format('d/m') }}</div>@endif</td>
                    <td class="text-right">
                        @can('agenda.editar')
                            <div class="flex flex-wrap justify-end gap-1">
                                <a href="{{ route('agenda.index', ['lembrete' => $l->id]) }}#form-agenda" class="btn btn-sm">Agendar</a>
                                @if ($l->status === 'pendente')
                                    @if ($l->cliente && ! $l->cliente->trashed() && $l->cliente->aceita_whatsapp && $l->veiculo && ! $l->veiculo->trashed())
                                        <form method="post" action="{{ route('lembretes.enviar', $l) }}" @unless($automatico) target="_blank" @endunless>@csrf<button class="btn-primario btn-sm">{{ $automatico ? 'Enviar' : 'Abrir WhatsApp' }}</button></form>
                                        @unless ($automatico)
                                            <form method="post" action="{{ route('lembretes.confirmar-manual', $l) }}" data-confirmar="Confirma que você enviou a mensagem pelo WhatsApp?">@csrf<button class="btn btn-sm">Confirmar envio</button></form>
                                        @endunless
                                    @else
                                        <span class="text-xs text-erro">Sem autorização ou cadastro excluído</span>
                                    @endif
                                    <form method="post" action="{{ route('lembretes.descartar', $l) }}">@csrf<button class="btn btn-sm text-suave">Descartar</button></form>
                                @endif
                            </div>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-10 text-center text-suave">Nenhum lembrete. Para gerar, configure o prazo de revisão nos serviços do catálogo (ex.: troca de óleo: 6 meses ou 10.000 km).</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $lembretes->links() }}
    </div>
</x-app>
