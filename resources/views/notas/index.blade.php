<x-app titulo="Notas fiscais">
    <h1 class="mb-1">Notas fiscais</h1>
    <p class="mb-5 text-suave">As notas são emitidas dentro de cada OS, depois do pagamento.</p>
    @if ($demonstracao)
        <div class="mb-5 rounded-md border border-[#f0d58a] bg-[#fff7df] px-4 py-3 text-[15px]">
            Modo demonstração: as notas não têm valor fiscal. Para emitir de verdade, cadastre o token da Focus NFe em
            @can('empresa.configurar')<a href="{{ route('empresa.edit') }}" class="font-semibold underline">Empresa</a>@else Empresa @endcan.
        </div>
    @endif
    <form class="mb-4 flex gap-2">
        <select name="status" class="input w-auto" aria-label="Situação">
            <option value="">Todas</option>
            @foreach (['processando' => 'Processando', 'autorizada' => 'Autorizadas', 'erro' => 'Com erro', 'cancelada' => 'Canceladas'] as $v => $r)<option value="{{ $v }}" @selected($status === $v)>{{ $r }}</option>@endforeach
        </select>
        <button class="btn">Filtrar</button>
    </form>
    <section class="card">
        @if ($notas->isEmpty())
            <p class="px-5 py-10 text-center text-suave">Nenhuma nota ainda.</p>
        @else
            <ul class="divide-y divide-linha">@include('notas._lista', ['notas' => $notas, 'mostrarOs' => true])</ul>
            {{ $notas->links() }}
        @endif
    </section>
</x-app>
