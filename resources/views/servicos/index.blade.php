@php use App\Support\Dinheiro; $pode = auth()->user()->can('servicos.gerenciar'); @endphp
<x-app titulo="Catálogo de serviços">
    <div class="mb-5">
        <h1>Catálogo de serviços</h1>
        <p class="text-suave">Serviços com preço padrão. No orçamento é só escolher; o valor ainda pode ser ajustado em cada OS.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1.6fr_1fr]">
        <div class="min-w-0">
            <form class="mb-4 flex gap-2" role="search">
                <label class="sr-only" for="busca">Buscar serviço</label>
                <input id="busca" name="busca" value="{{ $busca }}" class="input max-w-sm" placeholder="Buscar serviço">
                <button class="btn">Buscar</button>
            </form>
            <div class="card overflow-x-auto">
                <table class="tabela">
                    <thead><tr><th>Serviço</th><th class="text-right">Preço</th><th class="hidden text-right sm:table-cell">Tempo</th><th class="hidden text-right sm:table-cell">Garantia</th><th class="hidden text-right md:table-cell">Revisão</th>@if($pode)<th></th>@endif</tr></thead>
                    <tbody>
                    @forelse ($servicos as $s)
                        <tr @class(['opacity-55' => ! $s->ativo])>
                            <td class="font-medium">{{ $s->nome }}@unless($s->ativo) <span class="text-xs text-suave">(inativo)</span>@endunless</td>
                            <td class="whitespace-nowrap text-right">{{ Dinheiro::formatar($s->preco_centavos) }}</td>
                            <td class="hidden whitespace-nowrap text-right sm:table-cell">{{ $s->tempo_minutos ? ($s->tempo_minutos >= 60 ? intdiv($s->tempo_minutos, 60).'h'.($s->tempo_minutos % 60 ? str_pad($s->tempo_minutos % 60, 2, '0', STR_PAD_LEFT) : '') : $s->tempo_minutos.' min') : '—' }}</td>
                            <td class="hidden whitespace-nowrap text-right sm:table-cell">{{ $s->garantia_dias !== null ? $s->garantia_dias.' dias' : '—' }}</td>
                            <td class="hidden whitespace-nowrap text-right md:table-cell">{{ collect([$s->revisao_meses ? $s->revisao_meses.' meses' : null, $s->revisao_km ? number_format($s->revisao_km, 0, ',', '.').' km' : null])->filter()->join(' / ') ?: '—' }}</td>
                            @if ($pode)<td class="text-right"><a href="{{ route('servicos.index', ['editar' => $s->id]) }}" class="text-sm font-semibold hover:underline">Editar</a></td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-10 text-center text-suave">Nenhum serviço no catálogo ainda.</td></tr>
                    @endforelse
                    </tbody>
                </table>
                {{ $servicos->links() }}
            </div>
        </div>

        @if ($pode)
            <section class="card card-corpo h-fit">
                <h2 class="mb-4">{{ $editando ? 'Editar serviço' : 'Novo serviço' }}</h2>
                <form method="post" action="{{ $editando ? route('servicos.update', $editando) : route('servicos.store') }}" class="space-y-4">
                    @csrf @if ($editando) @method('put') @endif
                    <x-campo nome="nome" rotulo="Nome" :valor="$editando?->nome" required maxlength="120" placeholder="Ex.: Alinhamento e balanceamento" />
                    <div class="grid grid-cols-2 gap-3">
                        <x-campo nome="preco" rotulo="Preço (R$)" :valor="$editando ? Dinheiro::campo($editando->preco_centavos) : ''" mascara="dinheiro" inputmode="decimal" required />
                        <x-campo nome="tempo_minutos" rotulo="Tempo (min)" tipo="number" min="1" :valor="$editando?->tempo_minutos" />
                    </div>
                    <x-campo nome="garantia_dias" rotulo="Garantia (dias)" tipo="number" min="0" :valor="$editando?->garantia_dias" ajuda="Deixe em branco para usar a garantia da OS." />
                    <div class="grid grid-cols-2 gap-3">
                        <x-campo nome="revisao_meses" rotulo="Lembrar em (meses)" tipo="number" min="1" :valor="$editando?->revisao_meses" />
                        <x-campo nome="revisao_km" rotulo="ou a cada (km)" tipo="number" min="100" step="100" :valor="$editando?->revisao_km" />
                    </div>
                    <p class="-mt-2 text-xs text-suave">Com isso, a entrega do carro cria um lembrete de revisão para o cliente.</p>
                    @error('preco_centavos')<p class="erro-msg">{{ $message }}</p>@enderror
                    @if ($editando)
                        <label class="flex items-center gap-2 text-[15px]"><input type="hidden" name="ativo" value="0"><input type="checkbox" name="ativo" value="1" class="h-5 w-5 accent-asfalto" @checked($editando->ativo)> Aparece nos orçamentos</label>
                    @endif
                    <div class="flex flex-wrap gap-2">
                        <button class="btn-primario">{{ $editando ? 'Salvar serviço' : 'Adicionar ao catálogo' }}</button>
                        @if ($editando)<a href="{{ route('servicos.index') }}" class="btn">Cancelar</a>@endif
                    </div>
                </form>
                @if ($editando)
                    <form method="post" action="{{ route('servicos.destroy', $editando) }}" class="mt-6 border-t border-linha pt-4" data-confirmar="Remover {{ $editando->nome }} do catálogo?">
                        @csrf @method('delete')
                        <button class="btn-perigo btn-sm">Remover do catálogo</button>
                    </form>
                @endif
            </section>
        @endif
    </div>
</x-app>
