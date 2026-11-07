@php use App\Support\Dinheiro; use App\Models\Empresa; @endphp
<x-app :titulo="$empresa->nome_fantasia">
    <h1 class="mb-1">{{ $empresa->nome_fantasia }}</h1>
    <p class="mb-4 text-suave">{{ $empresa->cnpj ? \App\Support\Documento::formatar($empresa->cnpj).' · ' : '' }}{{ $empresa->email }} · {{ $empresa->telefone }} · desde {{ $empresa->created_at->format('d/m/Y') }}</p>
    @include('plataforma._menu')
    <div class="mb-6 grid gap-4 sm:grid-cols-4">
        <div class="card card-corpo"><div class="text-sm text-suave">Usuários ativos</div><div class="mt-1 text-2xl font-semibold">{{ $uso['usuarios'] }}</div></div>
        <div class="card card-corpo"><div class="text-sm text-suave">OS no mês</div><div class="mt-1 text-2xl font-semibold">{{ $uso['os_mes'] }}</div></div>
        <div class="card card-corpo"><div class="text-sm text-suave">Consultas de placa no mês</div><div class="mt-1 text-2xl font-semibold">{{ $uso['placas_mes'] }}</div></div>
        <div class="card card-corpo"><div class="text-sm text-suave">Notas no mês</div><div class="mt-1 text-2xl font-semibold">{{ $uso['notas_mes'] }}</div></div>
    </div>
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card card-corpo">
            <h2 class="mb-4">Assinatura</h2>
            <form method="post" action="{{ route('plataforma.empresa.update', $empresa) }}" class="space-y-4">
                @csrf @method('put')
                <label class="campo"><span>Plano</span><select name="plano_id" class="input">@foreach($planos as $p)<option value="{{ $p->id }}" @selected($empresa->plano_id === $p->id)>{{ $p->nome }} · {{ $p->precoFormatado() }}</option>@endforeach</select></label>
                <label class="campo"><span>Situação</span><select name="assinatura_status" class="input">@foreach(Empresa::STATUS as $k => $r)<option value="{{ $k }}" @selected($empresa->assinatura_status === $k)>{{ $r }}</option>@endforeach</select></label>
                <x-campo nome="trial_ate" rotulo="Teste até" tipo="date" :valor="$empresa->trial_ate?->toDateString()" ajuda="Para dar mais dias de teste" />
                <label class="flex items-center gap-2 text-[15px]"><input type="hidden" name="ativo" value="0"><input type="checkbox" name="ativo" value="1" class="h-4 w-4 accent-asfalto" @checked($empresa->ativo)> Acesso liberado (desmarcar derruba todos os usuários)</label>
                <button class="btn-primario">Salvar</button>
            </form>
            <p class="mt-4 text-sm text-suave">Gateway: cliente {{ $empresa->cobranca_cliente_id ?? '—' }} · assinatura {{ $empresa->cobranca_assinatura_id ?? '—' }}</p>
        </section>
        <section class="card overflow-x-auto">
            <div class="border-b border-linha px-5 py-4"><h2>Faturas</h2></div>
            <table class="tabela"><thead><tr><th>Vencimento</th><th class="text-right">Valor</th><th>Situação</th></tr></thead><tbody>
                @forelse ($faturas as $f)<tr><td>{{ $f->vencimento?->format('d/m/Y') }}</td><td class="text-right">{{ Dinheiro::formatar($f->valor_centavos) }}</td><td>{{ $f->status }}</td></tr>@empty<tr><td colspan="3" class="py-6 text-center text-suave">Sem faturas.</td></tr>@endforelse
            </tbody></table>
        </section>
    </div>
</x-app>
