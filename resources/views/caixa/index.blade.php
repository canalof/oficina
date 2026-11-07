@php use App\Support\Dinheiro; use App\Services\Financeiro\Financeiro; @endphp
<x-app titulo="Caixa">
    <h1 class="mb-5">Caixa</h1>

    @if (! $caixa)
        <section class="card card-corpo mb-6 max-w-lg">
            <h2 class="mb-1">O caixa está fechado</h2>
            <p class="mb-4 text-[15px] text-suave">Abra informando quanto de troco tem na gaveta. Pix e cartão podem ser recebidos com o caixa fechado; dinheiro, não.</p>
            <form method="post" action="{{ route('caixa.abrir') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <x-campo nome="troco" rotulo="Troco inicial (R$)" mascara="dinheiro" inputmode="decimal" valor="0,00" />
                <button class="btn-primario">Abrir caixa</button>
            </form>
        </section>
    @else
        @php $porForma = $caixa->recebidoPorForma(); @endphp
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card card-corpo border-l-4 border-l-sinal"><div class="text-sm text-suave">Dinheiro na gaveta</div><div class="mt-1 text-3xl font-semibold">{{ Dinheiro::formatar($caixa->dinheiroEsperado()) }}</div><div class="text-sm text-suave">aberto {{ $caixa->aberto_em->format('d/m H:i') }} por {{ $caixa->abertoPor?->name }}</div></div>
            <div class="card card-corpo"><div class="text-sm text-suave">Recebido hoje</div><div class="mt-1 text-3xl font-semibold">{{ Dinheiro::formatar(array_sum($porForma)) }}</div></div>
            <div class="card card-corpo sm:col-span-2">
                <div class="text-sm text-suave">Por forma de pagamento</div>
                <dl class="mt-2 grid grid-cols-2 gap-x-6 gap-y-1 text-[15px]">
                    @forelse ($porForma as $f => $v)
                        <div class="flex justify-between gap-3"><dt>{{ Financeiro::FORMAS[$f] ?? $f }}</dt><dd class="font-semibold">{{ Dinheiro::formatar($v) }}</dd></div>
                    @empty
                        <p class="text-suave">Nada recebido ainda.</p>
                    @endforelse
                </dl>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1.6fr_1fr] [&>*]:min-w-0">
            <section class="card overflow-x-auto">
                <div class="border-b border-linha px-5 py-4"><h2>Movimentações deste caixa</h2></div>
                @include('caixa._movimentos')
            </section>
            <div class="space-y-6">
                <section class="card card-corpo">
                    <h2 class="mb-3">Reforço ou retirada</h2>
                    <form method="post" action="{{ route('caixa.movimento') }}" class="space-y-3">
                        @csrf
                        <div class="inline-flex overflow-hidden rounded-md border border-[#c9cfd6] text-sm">
                            @foreach (['sangria' => 'Retirar dinheiro', 'suprimento' => 'Colocar troco'] as $v => $r)
                                <label class="cursor-pointer border-r border-[#c9cfd6] last:border-r-0"><input type="radio" name="tipo" value="{{ $v }}" class="peer sr-only" @checked($v === 'sangria')><span class="block px-3 py-1.5 font-medium peer-checked:bg-asfalto peer-checked:text-white">{{ $r }}</span></label>
                            @endforeach
                        </div>
                        <x-campo nome="valor" rotulo="Valor (R$)" mascara="dinheiro" inputmode="decimal" required />
                        <x-campo nome="descricao" rotulo="Motivo" placeholder="Ex.: depósito no banco, compra de material" required />
                        <button class="btn">Registrar</button>
                    </form>
                </section>
                <section class="card card-corpo">
                    <h2 class="mb-1">Fechar o caixa</h2>
                    <p class="mb-3 text-sm text-suave">Conte o dinheiro da gaveta. O sistema compara com o esperado.</p>
                    <form method="post" action="{{ route('caixa.fechar') }}" class="space-y-3" data-confirmar="Fechar o caixa agora?">
                        @csrf
                        <x-campo nome="contado" rotulo="Dinheiro contado (R$)" mascara="dinheiro" inputmode="decimal" required />
                        <x-campo nome="observacao" rotulo="Observação" />
                        <button class="btn-primario">Fechar caixa</button>
                    </form>
                </section>
            </div>
        </div>
    @endif

    @if ($anteriores->isNotEmpty())
        <section class="card mt-6 overflow-x-auto">
            <div class="border-b border-linha px-5 py-4"><h2>Caixas anteriores</h2></div>
            <table class="tabela">
                <thead><tr><th>Aberto</th><th>Fechado</th><th class="text-right">Esperado</th><th class="text-right">Contado</th><th class="text-right">Diferença</th></tr></thead>
                <tbody>
                @foreach ($anteriores as $c)
                    @php $dif = (int) $c->dinheiro_contado_centavos - (int) $c->dinheiro_esperado_centavos; @endphp
                    <tr>
                        <td><a href="{{ route('caixa.show', $c) }}" class="font-semibold hover:underline">{{ $c->aberto_em->format('d/m/Y H:i') }}</a></td>
                        <td class="text-sm">{{ $c->fechado_em->format('d/m H:i') }} · {{ $c->fechadoPor?->name }}</td>
                        <td class="text-right">{{ Dinheiro::formatar($c->dinheiro_esperado_centavos) }}</td>
                        <td class="text-right">{{ Dinheiro::formatar($c->dinheiro_contado_centavos) }}</td>
                        <td @class(['text-right font-semibold', 'text-ok' => $dif === 0, 'text-erro' => $dif !== 0])>{{ $dif === 0 ? 'bateu' : ($dif > 0 ? '+' : '−').' '.Dinheiro::formatar(abs($dif)) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif
</x-app>
