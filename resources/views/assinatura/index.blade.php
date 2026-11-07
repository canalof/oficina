@php use App\Support\Dinheiro; use App\Models\Empresa; @endphp
<x-app titulo="Assinatura">
    <h1 class="mb-1">Assinatura do Torque</h1>
    @if ($empresa->bloqueada())
        <div class="mb-5 mt-3 rounded-md border border-[#ecc3bf] bg-[#fcf0ef] px-4 py-3 text-[15px] text-[#8c241d]" role="alert">
            {{ $empresa->assinatura_status === 'trial' ? 'O teste grátis terminou.' : 'O acesso está suspenso.' }} Escolha um plano para voltar a usar. Seus dados continuam guardados.
        </div>
    @endif
    <div class="mb-6 mt-4 grid gap-4 sm:grid-cols-3">
        <div class="card card-corpo"><div class="text-sm text-suave">Plano</div><div class="mt-1 text-2xl font-semibold">{{ $empresa->plano?->nome ?? '—' }}</div><div class="text-sm text-suave">{{ $empresa->plano?->precoFormatado() }}/mês</div></div>
        <div class="card card-corpo"><div class="text-sm text-suave">Situação</div><div class="mt-1 text-2xl font-semibold">{{ Empresa::STATUS[$empresa->assinatura_status] ?? $empresa->assinatura_status }}</div>
            <div class="text-sm text-suave">@if($empresa->diasDeTeste() !== null) {{ $empresa->diasDeTeste() }} dia(s) de teste @elseif($empresa->proximo_vencimento) próxima cobrança {{ $empresa->proximo_vencimento->format('d/m/Y') }} @endif</div></div>
        <div class="card card-corpo"><div class="text-sm text-suave">Usuários ativos</div><div class="mt-1 text-2xl font-semibold">{{ $usuarios }}{{ $empresa->plano?->limite_usuarios ? ' de '.$empresa->plano->limite_usuarios : '' }}</div></div>
    </div>

    <h2 class="mb-3">Planos</h2>
    <div class="mb-6 grid gap-4 md:grid-cols-3">
        @foreach ($planos as $p)
            @php $atual = $empresa->plano_id === $p->id && in_array($empresa->assinatura_status, ['ativa', 'inadimplente'], true); @endphp
            <section @class(['card card-corpo flex flex-col', 'border-2 border-asfalto' => $empresa->plano_id === $p->id])>
                <h3 class="text-lg font-semibold">{{ $p->nome }}</h3>
                <div class="mt-1 text-3xl font-semibold">{{ $p->precoFormatado() }}<span class="text-sm font-normal text-suave">/mês</span></div>
                <ul class="mt-3 flex-1 space-y-1 text-[15px]">
                    <li>{{ $p->limiteTexto($p->limite_usuarios, 'usuários') }}</li>
                    <li>{{ $p->limite_consultas_placa ? $p->limite_consultas_placa.' consultas de placa por mês' : 'Consultas de placa sem limite' }}</li>
                    <li>OS, estoque, caixa, notas fiscais e agenda</li>
                    <li @class(['text-suave line-through' => ! $p->whatsapp_automatico])>WhatsApp automático</li>
                    @if (($p->limite_lojas ?? 1) > 1)<li>Até {{ $p->limite_lojas }} lojas</li>@endif
                </ul>
                @if ($pode)
                    @if ($atual)
                        <p class="mt-4 text-[15px] font-semibold text-ok">Seu plano</p>
                    @else
                        <form method="post" action="{{ route('assinatura.assinar') }}" class="mt-4" data-confirmar="Assinar o plano {{ $p->nome }} por {{ $p->precoFormatado() }} por mês?">
                            @csrf <input type="hidden" name="plano" value="{{ $p->slug }}">
                            <button class="btn-primario w-full">{{ $empresa->cobranca_assinatura_id ? 'Mudar para '.$p->nome : 'Assinar '.$p->nome }}</button>
                        </form>
                    @endif
                @endif
            </section>
        @endforeach
    </div>

    <section class="card overflow-x-auto">
        <div class="border-b border-linha px-5 py-4"><h2>Faturas</h2></div>
        <table class="tabela">
            <thead><tr><th>Vencimento</th><th class="text-right">Valor</th><th>Situação</th><th></th></tr></thead>
            <tbody>
            @forelse ($faturas as $f)
                <tr>
                    <td>{{ $f->vencimento?->format('d/m/Y') }}</td>
                    <td class="text-right">{{ Dinheiro::formatar($f->valor_centavos) }}</td>
                    <td>{{ ['pendente' => 'Aberta', 'paga' => 'Paga', 'vencida' => 'Vencida', 'estornada' => 'Estornada', 'cancelada' => 'Cancelada'][$f->status] ?? $f->status }}</td>
                    <td class="text-right">@if($f->url && in_array($f->status, ['pendente', 'vencida']))<a href="{{ $f->url }}" target="_blank" rel="noopener" class="btn-primario btn-sm">Pagar</a>@elseif($f->url)<a href="{{ $f->url }}" target="_blank" rel="noopener" class="btn btn-sm">Ver</a>@endif</td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-8 text-center text-suave">Nenhuma fatura ainda.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
</x-app>
