@php use App\Models\Produto; @endphp
<x-app titulo="Novo balanço">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1>Novo balanço de estoque</h1>
            <p class="text-suave">Conte os produtos e digite a quantidade. Só muda o que estiver diferente; em branco não mexe.</p>
        </div>
        <a href="{{ route('balancos.index') }}" class="btn">Voltar</a>
    </div>

    @if (session('erros_planilha'))
        <ul class="mb-5 max-h-64 list-disc overflow-y-auto rounded-md border border-[#ecc3bf] bg-[#fcf0ef] py-3 pl-9 pr-4 text-sm text-[#8c241d]">@foreach (session('erros_planilha') as $e)<li>{{ $e }}</li>@endforeach</ul>
    @endif

    @if ($planilha)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-md border border-[#f0d58a] bg-[#fff7df] px-4 py-3 text-[15px]">
            <span>Quantidades da planilha carregadas ({{ count($planilha) }} produtos). Confira e clique em <strong>Aplicar balanço</strong>. Nada mudou ainda.</span>
            <a href="{{ route('balancos.create', ['descartar' => 1]) }}" class="font-semibold underline">Descartar planilha</a>
        </div>
    @else
        <div class="mb-4 grid gap-4 lg:grid-cols-[1fr_auto]">
            <form class="flex flex-wrap items-end gap-2" role="search">
                <label class="campo"><span>Buscar</span><input name="busca" value="{{ $filtros['busca'] ?? '' }}" class="input w-56" placeholder="Nome, código ou marca"></label>
                @if ($categorias->isNotEmpty())
                    <label class="campo"><span>Categoria</span><select name="categoria" class="input w-auto"><option value="">Todas</option>@foreach ($categorias as $c)<option value="{{ $c->id }}" @selected((string) ($filtros['categoria'] ?? '') === (string) $c->id)>{{ $c->nome }}</option>@endforeach</select></label>
                @endif
                @if ($locais->isNotEmpty())
                    <label class="campo"><span>Local</span><select name="local" class="input w-auto"><option value="">Todos</option>@foreach ($locais as $l)<option @selected(($filtros['local'] ?? '') === $l)>{{ $l }}</option>@endforeach</select></label>
                @endif
                <button class="btn">Filtrar</button>
                <a href="{{ route('balancos.planilha', $filtros) }}" class="btn">Baixar planilha</a>
            </form>
            <form method="post" action="{{ route('balancos.planilha.enviar') }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-2">
                @csrf
                <label class="campo"><span>Planilha preenchida (.csv)</span>
                    <input type="file" name="arquivo" accept=".csv,text/csv" required class="input w-64 py-1.5 text-sm file:mr-3 file:rounded file:border-0 file:bg-asfalto file:px-3 file:py-1 file:text-white">
                </label>
                <button class="btn">Enviar planilha</button>
            </form>
        </div>
        @if ($cortou)
            <p class="mb-4 text-sm text-erro">A lista mostra os primeiros {{ \App\Http\Controllers\BalancoController::LIMITE }} produtos. Filtre por categoria ou local para contar o resto em outro balanço.</p>
        @endif
    @endif

    @if ($produtos->isEmpty())
        <div class="card card-corpo text-center text-suave">Nenhum produto ativo com esse filtro.</div>
    @else
        <form method="post" action="{{ route('balancos.store') }}" data-balanco>
            @csrf
            <div class="card card-corpo mb-4 flex flex-wrap items-end gap-3">
                <label class="campo"><span>Leitor de código de barras</span>
                    <input type="text" class="input w-64" data-bipar inputmode="numeric" autocomplete="off" placeholder="Bipe ou digite o código e Enter">
                </label>
                <label class="flex items-center gap-2 pb-2.5 text-[15px]"><input type="checkbox" data-somar checked> Somar 1 a cada leitura</label>
                <p class="pb-2.5 text-sm text-suave" data-bipar-status aria-live="polite"></p>
            </div>

            <div class="card overflow-x-auto">
                <table class="tabela">
                    <thead><tr><th>Produto</th><th class="hidden sm:table-cell">Local</th><th class="text-right">No sistema</th><th class="w-36 text-right">Contado</th><th class="text-right">Diferença</th></tr></thead>
                    <tbody>
                    @foreach ($produtos as $p)
                        @php
                            $valor = old("contado.{$p->id}", isset($planilha[$p->id]) ? str_replace('.', ',', rtrim(rtrim(number_format($planilha[$p->id], 3, '.', ''), '0'), '.')) : '');
                        @endphp
                        <tr data-linha data-sistema="{{ (float) $p->estoque }}" data-codigos="{{ implode('|', array_filter([$p->codigo, $p->codigo_barras, $p->referencia_fabricante])) }}">
                            <td><span class="font-semibold">{{ $p->nome }}</span><div class="text-xs text-suave">{{ $p->codigo ?: $p->codigo_barras }}</div></td>
                            <td class="hidden text-sm sm:table-cell">{{ $p->localizacao }}</td>
                            <td class="whitespace-nowrap text-right">{{ $p->estoqueFormatado() }}</td>
                            <td class="text-right">
                                <input type="hidden" name="visto[{{ $p->id }}]" value="{{ (float) $p->estoque }}">
                                <label class="sr-only" for="contado-{{ $p->id }}">Contado de {{ $p->nome }}</label>
                                <input id="contado-{{ $p->id }}" name="contado[{{ $p->id }}]" value="{{ $valor }}" inputmode="decimal" autocomplete="off" class="input w-28 text-right" data-contado>
                                @error("contado.{$p->id}")<p class="erro-msg text-left">{{ $message }}</p>@enderror
                            </td>
                            <td class="whitespace-nowrap text-right font-semibold" data-diferenca></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="sticky bottom-0 mt-4 flex flex-wrap items-end gap-3 border-t border-linha bg-fundo py-3">
                <label class="campo min-w-[240px] flex-1"><span>Observação (opcional)</span><input name="observacao" value="{{ old('observacao') }}" maxlength="200" class="input" placeholder="Ex.: balanço de fim de mês, prateleira A"></label>
                <p class="pb-2.5 text-[15px]" data-resumo aria-live="polite">Nenhum produto contado ainda.</p>
                <button class="btn-primario">Aplicar balanço</button>
                @error('contado')<p class="erro-msg w-full">{{ $message }}</p>@enderror
            </div>
        </form>
    @endif
</x-app>
