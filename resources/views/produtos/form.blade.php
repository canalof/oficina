@php use App\Support\Dinheiro; use App\Models\Produto; $novo = ! $produto->exists; @endphp
<x-app :titulo="$novo ? 'Novo produto' : 'Editar produto'">
    <h1 class="mb-5">{{ $novo ? 'Novo produto' : 'Editar '.$produto->nome }}</h1>
    <form method="post" data-avisar-saida action="{{ $novo ? route('produtos.store') : route('produtos.update', $produto) }}" class="card card-corpo max-w-3xl">
        @csrf @unless($novo) @method('put') @endunless
        @include('partials.duplicados')
        <div class="grid gap-4 md:grid-cols-6">
            <x-campo nome="nome" rotulo="Nome" :valor="$produto->nome" required class="md:col-span-4" placeholder="Ex.: Pastilha de freio dianteira HB20" />
            <label class="campo md:col-span-2"><span>Fabricante / marca</span>
                <select name="fabricante_id" class="input"><option value="">—</option>@foreach($fabricantes as $f)<option value="{{ $f->id }}" @selected((int) old('fabricante_id', $produto->fabricante_id) === $f->id)>{{ $f->nome }}</option>@endforeach</select>
            </label>
            <label class="campo md:col-span-2"><span>Categoria</span>
                <select name="categoria_id" class="input"><option value="">—</option>@foreach($categorias as $c)<option value="{{ $c->id }}" @selected((int) old('categoria_id', $produto->categoria_id) === $c->id)>{{ $c->nome }}</option>@endforeach</select>
            </label>
            <p class="-mt-2 text-xs text-suave md:col-span-6">Falta categoria ou fabricante? Cadastre em <a href="{{ route('auxiliar.index', 'categorias') }}" class="underline" target="_blank">Categorias</a> ou <a href="{{ route('auxiliar.index', 'fabricantes') }}" class="underline" target="_blank">Fabricantes</a>.</p>
            <x-campo nome="codigo" rotulo="Código interno" :valor="$produto->codigo" class="md:col-span-2" />
            <x-campo nome="codigo_barras" rotulo="Código de barras" :valor="$produto->codigo_barras" inputmode="numeric" class="md:col-span-2" />
            <x-campo nome="referencia_fabricante" rotulo="Referência do fabricante" :valor="$produto->referencia_fabricante" class="md:col-span-2" />
            <x-campo nome="ncm" rotulo="NCM" :valor="$produto->ncm" inputmode="numeric" maxlength="8" ajuda="Necessário para emitir nota" class="md:col-span-2" />
            <label class="campo md:col-span-2"><span>Unidade</span>
                <select name="unidade" class="input">@foreach(Produto::UNIDADES as $u => $r)<option value="{{ $u }}" @selected(old('unidade', $produto->unidade) === $u)>{{ $u }} · {{ $r }}</option>@endforeach</select>
            </label>
            <x-campo nome="localizacao" rotulo="Onde fica" :valor="$produto->localizacao" placeholder="Ex.: Prateleira B3" class="md:col-span-2" />
            <x-campo nome="estoque_minimo" rotulo="Estoque mínimo" :valor="(float) $produto->estoque_minimo ? Produto::formatarQtd($produto->estoque_minimo) : ''" inputmode="decimal" ajuda="Avisa quando chegar nesse número" class="md:col-span-3" />
            <x-campo nome="custo" rotulo="Custo médio (R$)" :valor="Dinheiro::campo($produto->custo_centavos)" mascara="dinheiro" inputmode="decimal" :ajuda="$novo ? 'Custo de compra' : 'Recalculado a cada entrada com custo'" class="md:col-span-3" />
            <x-campo nome="preco" rotulo="Preço de venda (R$)" :valor="Dinheiro::campo($produto->preco_centavos)" mascara="dinheiro" inputmode="decimal" class="md:col-span-3" />
            @if ($novo)
                <x-campo nome="estoque_inicial" rotulo="Quantidade em estoque agora" inputmode="decimal" class="md:col-span-3" />
            @else
                <label class="flex items-center gap-2 self-end pb-3 text-[15px] md:col-span-3"><input type="hidden" name="ativo" value="0"><input type="checkbox" name="ativo" value="1" class="h-5 w-5 accent-asfalto" @checked($produto->ativo)> Produto ativo</label>
            @endif
        </div>
        <div class="mt-5 flex gap-3">
            <button class="btn-primario">{{ $novo ? 'Cadastrar produto' : 'Salvar produto' }}</button>
            <a href="{{ $novo ? route('produtos.index') : route('produtos.show', $produto) }}" class="btn">Voltar</a>
        </div>
    </form>
</x-app>
