<x-app :titulo="$fornecedor->exists ? 'Editar fornecedor' : 'Novo fornecedor'">
    <h1 class="mb-5">{{ $fornecedor->exists ? $fornecedor->nome : 'Novo fornecedor' }}</h1>
    <form method="post" data-avisar-saida action="{{ $fornecedor->exists ? route('fornecedores.update', $fornecedor) : route('fornecedores.store') }}" class="card card-corpo max-w-3xl">
        @csrf @if($fornecedor->exists) @method('put') @endif
        @include('partials.duplicados')
        <div class="grid gap-4 md:grid-cols-6">
            <x-campo nome="nome" rotulo="Nome" :valor="$fornecedor->nome" required class="md:col-span-4" />
            <x-campo nome="cpf_cnpj" rotulo="CNPJ ou CPF" :valor="$fornecedor->documentoFormatado()" mascara="documento" class="md:col-span-2" />
            <x-campo nome="contato" rotulo="Vendedor / contato" :valor="$fornecedor->contato" class="md:col-span-2" />
            <x-campo nome="telefone" rotulo="Telefone" :valor="$fornecedor->telefone" mascara="telefone" class="md:col-span-2" />
            <x-campo nome="email" rotulo="E-mail" tipo="email" :valor="$fornecedor->email" class="md:col-span-2" />
            <x-campo nome="cidade" rotulo="Cidade" :valor="$fornecedor->cidade" class="md:col-span-4" />
            <x-campo nome="uf" rotulo="UF" :valor="$fornecedor->uf" maxlength="2" class="md:col-span-2" />
            <label class="campo md:col-span-6"><span>Observações</span><textarea name="observacoes" rows="2" class="input">{{ old('observacoes', $fornecedor->observacoes) }}</textarea></label>
        </div>
        <div class="mt-5 flex flex-wrap gap-3"><button class="btn-primario">Salvar fornecedor</button><a href="{{ route('fornecedores.index') }}" class="btn">Voltar</a>@if($fornecedor->exists && auth()->user()->can('auditoria.ver'))<a href="{{ route('historico', ['fornecedor', $fornecedor->id]) }}" class="btn">Ver histórico</a>@endif</div>
    </form>
</x-app>
