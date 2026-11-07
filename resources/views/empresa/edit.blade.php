<x-app titulo="Empresa">
    <h1 class="mb-1">Dados da empresa</h1>
    <p class="mb-5 text-suave">Aparecem nos orçamentos, nas ordens de serviço e nas notas fiscais.</p>
    <form method="post" action="{{ route('empresa.update') }}" class="card card-corpo max-w-3xl">
        @csrf @method('put')
        <div class="grid gap-4 md:grid-cols-6">
            <x-campo nome="nome_fantasia" rotulo="Nome fantasia" :valor="$empresa->nome_fantasia" required class="md:col-span-3" />
            <x-campo nome="razao_social" rotulo="Razão social" :valor="$empresa->razao_social" class="md:col-span-3" />
            <x-campo nome="cnpj" rotulo="CNPJ" :valor="$empresa->cnpj ? \App\Support\Documento::formatar($empresa->cnpj) : null" mascara="documento" class="md:col-span-2" />
            <x-campo nome="telefone" rotulo="Telefone" :valor="$empresa->telefone" mascara="telefone" class="md:col-span-2" />
            <x-campo nome="email" rotulo="E-mail" tipo="email" :valor="$empresa->email" class="md:col-span-2" />
            <x-campo nome="cidade" rotulo="Cidade" :valor="$empresa->cidade" class="md:col-span-4" />
            <x-campo nome="uf" rotulo="UF" :valor="$empresa->uf" maxlength="2" class="md:col-span-2" />
        </div>
        <button class="btn-primario mt-5">Salvar</button>
    </form>
</x-app>
