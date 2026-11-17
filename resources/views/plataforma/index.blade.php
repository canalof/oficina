@php use App\Support\Dinheiro; use App\Models\Empresa; @endphp
<x-app titulo="Plataforma">
    <h1 class="mb-1">Plataforma Torque</h1>
    <p class="mb-4 text-suave">Visão do dono do SaaS. Daqui não se vê clientes, OS nem o financeiro das oficinas.</p>
    @include('plataforma._menu')
    @if ($status === Empresa::CADASTRO_PENDENTE)
        <h2 class="mb-1">Oficinas aguardando aprovação</h2>
        <p class="mb-4 text-suave">As solicitações enviadas pelo cadastro público aparecem aqui. Abra a oficina para revisar e liberar o acesso.</p>
    @else
    <a href="{{ route('plataforma.index', ['status' => Empresa::CADASTRO_PENDENTE]) }}" class="card card-corpo mb-4 block hover:border-asfalto-3">
        <span class="font-semibold">Cadastros aguardando liberação</span>
        <span class="rotulo ml-2 border-linha">{{ $contagem[Empresa::CADASTRO_PENDENTE] ?? 0 }}</span>
        <span class="mt-1 block text-sm text-suave">Ver pendentes e liberar oficinas</span>
    </a>
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card card-corpo"><div class="text-sm text-suave">Receita mensal (ativas)</div><div class="mt-1 text-3xl font-semibold">{{ Dinheiro::formatar($mrr) }}</div></div>
        @foreach (['ativa' => 'Ativas', 'trial' => 'Em teste', 'inadimplente' => 'Em atraso', 'suspensa' => 'Suspensas'] as $k => $r)
            <a href="{{ route('plataforma.index', ['status' => $k]) }}" class="card card-corpo hover:border-asfalto-3"><div class="text-sm text-suave">{{ $r }}</div><div @class(['mt-1 text-3xl font-semibold', 'text-erro' => in_array($k, ['inadimplente', 'suspensa']) && ($contagem[$k] ?? 0)])>{{ $contagem[$k] ?? 0 }}</div></a>
        @endforeach
    </div>
    @if ($webhooksComErro)<p class="mb-4 text-[15px] text-erro">{{ $webhooksComErro }} aviso(s) do gateway com erro nos últimos 7 dias. <a href="{{ route('plataforma.webhooks') }}" class="font-semibold underline">Ver</a></p>@endif
    @endif
    <form class="mb-4 flex gap-2">@if($status)<input type="hidden" name="status" value="{{ $status }}">@endif<input name="busca" value="{{ request('busca') }}" class="input max-w-xs" placeholder="Buscar oficina" aria-label="Buscar oficina"><button class="btn">Buscar</button>@if($status || request('busca'))<a href="{{ route('plataforma.index') }}" class="btn">Limpar</a>@endif</form>
    <div class="card overflow-x-auto">
        <table class="tabela">
            <thead><tr><th>Oficina</th><th>Plano</th><th>Situação</th><th class="hidden sm:table-cell">Teste até / próxima cobrança</th><th class="text-right">Usuários</th><th class="hidden md:table-cell">Desde</th></tr></thead>
            <tbody>
            @forelse ($empresas as $e)
                <tr>
                    <td>
                        <a href="{{ route('plataforma.empresa', $e) }}" class="font-semibold hover:underline">{{ $e->nome_fantasia }}</a>
                        @if ($e->assinatura_status === Empresa::CADASTRO_PENDENTE)
                            <div class="mt-2"><a href="{{ route('plataforma.empresa', $e) }}" class="btn btn-sm">Revisar cadastro</a></div>
                        @else
                            @unless($e->ativo)<span class="ml-1 text-xs text-erro">(desativada)</span>@endunless
                            <div class="mt-2"><a href="{{ route('plataforma.empresa', $e) }}" class="btn btn-sm">Editar oficina</a></div>
                        @endif
                    </td>
                    <td>{{ $e->plano?->nome }}</td>
                    <td><span @class(['rotulo', 'border-[#b7dcc6] bg-[#eef8f2] text-ok' => $e->assinatura_status === 'ativa', 'border-[#f0d58a] bg-[#fff7df] text-sinal-escuro' => in_array($e->assinatura_status, [Empresa::CADASTRO_PENDENTE, 'trial', 'inadimplente']), 'border-[#ecc3bf] bg-[#fcf0ef] text-erro' => in_array($e->assinatura_status, ['suspensa', 'cancelada'])])>{{ $e->assinatura_status === Empresa::CADASTRO_PENDENTE ? 'Aguardando liberação' : (Empresa::STATUS[$e->assinatura_status] ?? $e->assinatura_status) }}</span></td>
                    <td class="hidden sm:table-cell">{{ ($e->assinatura_status === 'trial' ? $e->trial_ate : $e->proximo_vencimento)?->format('d/m/Y') }}</td>
                    <td class="text-right">{{ $e->usuarios_count }}</td>
                    <td class="hidden md:table-cell">{{ $e->created_at->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-suave">{{ $status === Empresa::CADASTRO_PENDENTE && ! request('busca') ? 'Nenhuma oficina aguardando aprovação.' : 'Nenhuma oficina encontrada.' }}</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $empresas->links() }}
    </div>
</x-app>
