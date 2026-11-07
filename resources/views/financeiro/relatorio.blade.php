@php use App\Support\Dinheiro; use App\Services\Financeiro\Financeiro; use App\Models\Produto; @endphp
<x-app titulo="Resumo do mês">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <h1>Resumo de {{ $mes->translatedFormat('F \d\e Y') }}</h1>
        <form class="flex items-end gap-2"><x-campo nome="mes" rotulo="Mês" tipo="month" :valor="$mes->format('Y-m')" /><button class="btn">Ver</button></form>
    </div>
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card card-corpo"><div class="text-sm text-suave">Carros entregues</div><div class="mt-1 text-3xl font-semibold">{{ $carros }}</div><div class="text-sm text-suave">ticket médio {{ Dinheiro::formatar($ticket) }}</div></div>
        <div class="card card-corpo"><div class="text-sm text-suave">Faturado (OS entregues)</div><div class="mt-1 text-3xl font-semibold">{{ Dinheiro::formatar($faturado) }}</div></div>
        <div class="card card-corpo"><div class="text-sm text-suave">Entrou no caixa e banco</div><div class="mt-1 text-3xl font-semibold text-ok">{{ Dinheiro::formatar($recebido) }}</div></div>
        <div class="card card-corpo"><div class="text-sm text-suave">Saiu (contas pagas)</div><div class="mt-1 text-3xl font-semibold text-erro">{{ Dinheiro::formatar($pago) }}</div><div class="text-sm text-suave">saldo {{ Dinheiro::formatar($recebido - $pago) }}</div></div>
    </div>
    <section class="card card-corpo mb-6">
        <h2 class="mb-3">Resultado das OS entregues</h2>
        <dl class="grid gap-4 text-[15px] sm:grid-cols-4">
            <div><dt class="text-sm text-suave">Faturado</dt><dd class="text-xl font-semibold">{{ Dinheiro::formatar($faturado) }}</dd></div>
            <div><dt class="text-sm text-suave">(−) Custo das peças</dt><dd class="text-xl font-semibold">{{ Dinheiro::formatar($custoPecas) }}</dd></div>
            <div><dt class="text-sm text-suave">(−) Comissões</dt><dd class="text-xl font-semibold">{{ Dinheiro::formatar($comissoesMes) }}</dd></div>
            <div><dt class="text-sm text-suave">= Margem de contribuição</dt><dd class="text-xl font-semibold">{{ Dinheiro::formatar($faturado - $custoPecas - $comissoesMes) }}@if($faturado)<span class="text-sm font-normal text-suave"> ({{ number_format(($faturado - $custoPecas - $comissoesMes) / $faturado * 100, 0) }}%)</span>@endif</dd></div>
        </dl>
        <p class="mt-2 text-xs text-suave">Gerencial, não substitui a contabilidade. Custo das peças pelo custo no momento da baixa; despesas fixas estão em "Despesas por categoria".</p>
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach (['os' => 'OS do mês', 'receber' => 'Contas a receber', 'pagar' => 'Contas a pagar'] as $t => $r)
                <a href="{{ route('exportar', ['tipo' => $t, 'mes' => $mes->format('Y-m')]) }}" class="btn btn-sm">Baixar planilha: {{ $r }}</a>
            @endforeach
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card card-corpo">
            <h2 class="mb-3">Recebido por forma</h2>
            <dl class="space-y-1.5 text-[15px]">@forelse($recebidoPorForma as $f => $v)<div class="flex justify-between"><dt>{{ Financeiro::FORMAS[$f] ?? $f }}</dt><dd class="font-semibold">{{ Dinheiro::formatar((int) $v) }}</dd></div>@empty<p class="text-suave">Nada recebido.</p>@endforelse</dl>
        </section>
        <section class="card card-corpo">
            <h2 class="mb-3">Despesas por categoria</h2>
            <dl class="space-y-1.5 text-[15px]">@forelse($pagoPorCategoria as $c => $v)<div class="flex justify-between"><dt>{{ $c }}</dt><dd class="font-semibold">{{ Dinheiro::formatar((int) $v) }}</dd></div>@empty<p class="text-suave">Nada pago.</p>@endforelse</dl>
        </section>
        <section class="card card-corpo">
            <h2 class="mb-3">Serviços que mais faturaram</h2>
            <ol class="space-y-1.5 text-[15px]">@forelse($topServicos as $s)<li class="flex justify-between gap-3"><span>{{ $s->descricao }} <span class="text-suave">× {{ $s->qtd }}</span></span><span class="font-semibold">{{ Dinheiro::formatar((int) $s->total) }}</span></li>@empty<p class="text-suave">Sem dados.</p>@endforelse</ol>
        </section>
        <section class="card card-corpo">
            <h2 class="mb-3">Peças mais vendidas</h2>
            <ol class="space-y-1.5 text-[15px]">@forelse($topPecas as $p)<li class="flex justify-between gap-3"><span>{{ $p->descricao }} <span class="text-suave">× {{ Produto::formatarQtd($p->qtd) }}</span></span><span class="font-semibold">{{ Dinheiro::formatar((int) $p->total) }}</span></li>@empty<p class="text-suave">Sem dados.</p>@endforelse</ol>
        </section>
    </div>
    @if ($recusadoMes)
        <p class="mt-6 text-[15px]">Itens recusados pelos clientes neste mês somam <strong>{{ Dinheiro::formatar($recusadoMes) }}</strong>. Eles aparecem na ficha de cada carro para oferecer de novo.</p>
    @endif
</x-app>
