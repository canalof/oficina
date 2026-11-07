<x-app :titulo="'Importar '.$tipo">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1>Importar {{ $tipo === 'clientes' ? 'clientes' : 'produtos' }} de planilha</h1>
        <a href="{{ route($tipo === 'clientes' ? 'clientes.index' : 'produtos.index') }}" class="btn">Voltar</a>
    </div>

    @if ($resultado)
        <section @class(['card card-corpo mb-5', 'border-l-4 border-l-ok' => ! $resultado['erros'], 'border-l-4 border-l-sinal' => $resultado['erros']])>
            <p class="text-[15px]"><strong>{{ $resultado['criados'] }}</strong> cadastrado(s) e <strong>{{ $resultado['atualizados'] }}</strong> atualizado(s).@if($resultado['erros']) {{ count($resultado['erros']) }} linha(s) com problema:@endif</p>
            @if ($resultado['erros'])
                <ul class="mt-2 max-h-64 list-disc overflow-y-auto pl-5 text-sm text-erro">@foreach($resultado['erros'] as $e)<li>{{ $e }}</li>@endforeach</ul>
            @endif
        </section>
    @endif

    <section class="card card-corpo max-w-2xl">
        <ol class="space-y-3 text-[15px]">
            <li><strong>1.</strong> Baixe o <a href="{{ route('importar.modelo', $tipo) }}" class="font-semibold underline">modelo da planilha</a> e preencha uma linha por {{ $tipo === 'clientes' ? 'cliente' : 'produto' }}.</li>
            <li><strong>2.</strong> Colunas: <span class="text-sm text-suave">{{ implode(', ', $colunas) }}</span>. Só "nome" é obrigatória.</li>
            <li><strong>3.</strong> {{ $tipo === 'clientes' ? 'Cliente com o mesmo CPF/CNPJ é atualizado em vez de duplicado.' : 'Produto com o mesmo código ou código de barras é atualizado (o estoque dele não muda; use a contagem). Fabricante e categoria novos são criados.' }}</li>
            <li><strong>4.</strong> No Excel, salve como <em>CSV (separado por vírgulas)</em> e envie aqui.</li>
        </ol>
        <form method="post" action="{{ route('importar.enviar', $tipo) }}" enctype="multipart/form-data" class="mt-5 flex flex-wrap items-end gap-3">
            @csrf
            <label class="campo min-w-[240px] flex-1"><span>Planilha (.csv)</span>
                <input type="file" name="arquivo" accept=".csv,text/csv" required class="input py-1.5 text-sm file:mr-3 file:rounded file:border-0 file:bg-asfalto file:px-3 file:py-1 file:text-white">
            </label>
            <button class="btn-primario">Importar</button>
            @error('arquivo')<p class="erro-msg w-full">{{ $message }}</p>@enderror
        </form>
    </section>
</x-app>
