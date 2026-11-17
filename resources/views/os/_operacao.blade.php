<section id="operacao-os" class="card card-corpo space-y-4">
    <h2>Compras e serviços terceirizados</h2>
    @if ($podeEditar && $st->noPatio())
        @can('compras.lancar')
            @foreach ($os->itens->where('tipo', 'peca')->where('situacao', 'aprovado') as $item)
                @php $compra = $os->comprasPendentes->firstWhere('item_id', $item->id); @endphp
                <details class="rounded border border-linha p-3">
                    <summary class="cursor-pointer font-medium">{{ $item->descricao }} · {{ $compra?->compra_item_id ? 'Entrada vinculada' : ($compra?->escolhida !== null ? 'Aguardando peça' : 'Cotações') }}</summary>
                    @if ($compra?->escolhida !== null)
                        <a class="btn btn-sm mt-2" href="{{ route('os.pedido-peca', [$os, $item]) }}">Abrir pedido de compra</a>
                    @endif
                    <form class="mt-3 space-y-3" method="post" action="{{ route('os.compra-peca', $os) }}">
                        @csrf @method('put')
                        <input type="hidden" name="item_id" value="{{ $item->id }}">
                        @foreach (range(0, 4) as $i)
                            <fieldset class="grid gap-2 rounded border border-linha p-2 sm:grid-cols-2">
                                <legend>Cotação {{ $i + 1 }}</legend>
                                @foreach (['fornecedor' => 'Fornecedor', 'valor' => 'Valor em centavos', 'frete' => 'Frete em centavos', 'previsao' => 'Previsão de chegada'] as $campo => $rotulo)
                                    <label class="campo"><span>{{ $rotulo }}</span><input class="input" name="cotacoes[{{ $i }}][{{ $campo }}]" type="{{ match($campo) { 'previsao' => 'date', 'fornecedor' => 'text', default => 'number' } }}" value="{{ $compra?->cotacoes[$i][$campo] ?? '' }}"></label>
                                @endforeach
                            </fieldset>
                        @endforeach
                        <label class="campo"><span>Cotação escolhida</span><select class="input" name="escolhida"><option value="">Em comparação</option>
                            @foreach (range(0, 4) as $i)<option value="{{ $i }}" @selected($compra?->escolhida === $i)>Cotação {{ $i + 1 }}</option>@endforeach
                        </select></label>
                        <label class="campo"><span>Item de XML já lançado</span><select class="input" name="compra_item_id"><option value="">Aguardando entrada</option>
                            @foreach ($comprasPorProduto->get($item->produto_id, collect()) as $entrada)
                                <option value="{{ $entrada->id }}" @selected($compra?->compra_item_id === $entrada->id)>NF {{ $entrada->compra->numero }} · {{ $entrada->descricao }} · {{ $entrada->quantidade }}</option>
                            @endforeach
                        </select></label>
                        <button class="btn-primario">Salvar cotações e vínculo</button>
                        <a class="btn" href="{{ route('compras.index') }}">Abrir notas de compra</a>
                    </form>
                </details>
            @endforeach
        @endcan
    @endif
    @foreach ($os->terceirizados as $terceirizado)
        <article class="rounded border border-linha p-3">
            <p class="font-semibold">{{ $terceirizado->servico }} · {{ $terceirizado->parceiro }}</p>
            <p class="text-sm">{{ $terceirizado->responsavel }} · {{ \App\Support\Dinheiro::formatar($terceirizado->custo_centavos) }} · {{ $terceirizado->previsao->format('d/m/Y') }}</p>
            <p class="text-sm">{{ ['aguardando'=>'Aguardando envio','enviado'=>'Aguardando retorno','recebido'=>'Recebido','cancelado'=>'Cancelado'][$terceirizado->status] }}</p>
            @if ($podeEditar && $st->noPatio())
                <form method="post" action="{{ route('os.terceirizados.update', [$os, $terceirizado]) }}" class="mt-2 flex flex-wrap items-end gap-2">
                    @csrf @method('put')
                    <label class="campo"><span>Situação</span><select class="input" name="status">
                        @foreach (['aguardando'=>'Aguardando envio','enviado'=>'Aguardando retorno','recebido'=>'Recebido','cancelado'=>'Cancelado'] as $valor => $rotulo)<option value="{{ $valor }}" @selected($terceirizado->status === $valor)>{{ $rotulo }}</option>@endforeach
                    </select></label>
                    <x-campo nome="previsao" rotulo="Previsão" tipo="date" :valor="$terceirizado->previsao->toDateString()" required />
                    <button class="btn btn-sm">Atualizar</button>
                </form>
            @endif
        </article>
    @endforeach
    @if ($podeEditar && $st->noPatio())
        <details>
            <summary class="cursor-pointer font-medium">Registrar serviço terceirizado</summary>
            <form class="mt-3 grid gap-2 sm:grid-cols-2" method="post" action="{{ route('os.terceirizados.store', $os) }}">
                @csrf
                <x-campo nome="servico" rotulo="Serviço" required maxlength="150" />
                <x-campo nome="parceiro" rotulo="Parceiro" required maxlength="150" />
                <x-campo nome="responsavel" rotulo="Responsável" required maxlength="120" />
                <x-campo nome="custo_centavos" rotulo="Custo combinado em centavos" tipo="number" min="0" required />
                <x-campo nome="previsao" rotulo="Previsão de retorno" tipo="date" required />
                <div class="self-end"><button class="btn-primario">Registrar</button></div>
            </form>
        </details>
    @endif
</section>

<section id="troca-oleo" class="card card-corpo space-y-3">
    <h2>Ficha de troca de óleo</h2>
    @if ($oleoAnterior)
        <p class="text-sm text-suave">Última ficha em OS entregue: {{ $oleoAnterior->realizada_em->format('d/m/Y') }} · {{ $oleoAnterior->especificacao }} · {{ $oleoAnterior->litros }} L · {{ $oleoAnterior->filtro ?: 'filtro não informado' }} · {{ number_format($oleoAnterior->km, 0, ',', '.') }} km</p>
    @endif
    @if ($os->trocaOleo)
        <p>Registrado nesta OS: {{ $os->trocaOleo->especificacao }} · {{ $os->trocaOleo->litros }} L · {{ $os->trocaOleo->realizada_em->format('d/m/Y') }}</p>
    @endif
    @if ($podeExecutar && $st !== \App\Support\StatusOs::Cancelada)
        <details>
            <summary class="cursor-pointer font-medium">{{ $os->trocaOleo ? 'Editar ficha' : 'Registrar troca realizada' }}</summary>
            <form class="mt-3 grid gap-2 sm:grid-cols-2" method="post" action="{{ route('os.troca-oleo', $os) }}">
                @csrf @method('put')
                <x-campo nome="especificacao" rotulo="Viscosidade e especificação" :valor="$os->trocaOleo?->especificacao" required maxlength="120" />
                <x-campo nome="litros" rotulo="Volume em litros" tipo="number" min="0.01" step="0.01" :valor="$os->trocaOleo?->litros" required />
                <x-campo nome="filtro" rotulo="Filtro utilizado" :valor="$os->trocaOleo?->filtro" maxlength="120" />
                <x-campo nome="km" rotulo="Quilometragem" tipo="number" min="0" :valor="$os->trocaOleo?->km ?? $os->km_entrada" required />
                <x-campo nome="realizada_em" rotulo="Data da troca" tipo="date" :valor="$os->trocaOleo?->realizada_em?->toDateString() ?? today()->toDateString()" required />
                <div class="self-end"><button class="btn-primario">Salvar ficha</button></div>
            </form>
        </details>
    @endif
</section>
