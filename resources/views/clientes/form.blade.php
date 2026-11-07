<x-app :titulo="$cliente->exists ? 'Editar cliente' : 'Novo cliente'">
    <h1 class="mb-1">{{ $cliente->exists ? 'Editar cliente' : 'Novo cliente' }}</h1>
    @if ($voltarPlaca)
        <p class="mb-5 text-suave">Depois de salvar, você confere os dados do carro <x-placa :placa="$voltarPlaca" class="align-middle" />.</p>
    @else
        <p class="mb-5 text-suave">Só o nome é obrigatório. Para emitir nota fiscal, preencha CPF/CNPJ e endereço.</p>
    @endif

    <form method="post" action="{{ $cliente->exists ? route('clientes.update', $cliente) : route('clientes.store') }}" class="space-y-5">
        @csrf
        @if ($cliente->exists) @method('put') @endif
        @if ($voltarPlaca)<input type="hidden" name="voltar_placa" value="{{ $voltarPlaca }}">@endif

        <section class="card card-corpo">
            <h2 class="mb-4">Dados</h2>
            <div class="grid gap-4 md:grid-cols-6">
                <x-campo nome="nome" rotulo="Nome ou razão social" :valor="$cliente->nome" required class="md:col-span-4" autofocus />
                <x-campo nome="cpf_cnpj" rotulo="CPF ou CNPJ" :valor="$cliente->documentoFormatado()" mascara="documento" inputmode="text" class="md:col-span-2" />
                <x-campo nome="whatsapp" rotulo="WhatsApp" :valor="$cliente->whatsapp" mascara="telefone" inputmode="tel" class="md:col-span-2" />
                <x-campo nome="telefone" rotulo="Outro telefone" :valor="$cliente->telefone" mascara="telefone" inputmode="tel" class="md:col-span-2" />
                <x-campo nome="email" rotulo="E-mail" tipo="email" :valor="$cliente->email" class="md:col-span-2" />
                <x-campo nome="ie" rotulo="Inscrição estadual" :valor="$cliente->ie" ajuda="Só para empresa contribuinte" class="md:col-span-2" />
                <x-campo nome="nascimento" rotulo="Nascimento" tipo="date" :valor="$cliente->nascimento?->format('Y-m-d')" class="md:col-span-2" />
                <label class="flex items-center gap-2 self-end pb-2.5 text-[15px] md:col-span-2">
                    <input type="hidden" name="aceita_whatsapp" value="0">
                    <input type="checkbox" name="aceita_whatsapp" value="1" class="h-4 w-4 accent-asfalto" @checked(old('aceita_whatsapp', $cliente->aceita_whatsapp))>
                    Aceita receber avisos pelo WhatsApp
                </label>
            </div>
        </section>

        <section class="card card-corpo">
            <h2 class="mb-4">Endereço</h2>
            <div class="grid gap-4 md:grid-cols-6">
                <x-campo nome="cep" rotulo="CEP" :valor="$cliente->cep" mascara="cep" inputmode="numeric" ajuda="Preenche o endereço sozinho" class="md:col-span-2" />
                <x-campo nome="logradouro" rotulo="Rua" :valor="$cliente->logradouro" class="md:col-span-3" />
                <x-campo nome="numero" rotulo="Número" :valor="$cliente->numero" class="md:col-span-1" />
                <x-campo nome="complemento" rotulo="Complemento" :valor="$cliente->complemento" class="md:col-span-2" />
                <x-campo nome="bairro" rotulo="Bairro" :valor="$cliente->bairro" class="md:col-span-2" />
                <x-campo nome="cidade" rotulo="Cidade" :valor="$cliente->cidade" class="md:col-span-1" />
                <x-campo nome="uf" rotulo="UF" :valor="$cliente->uf" maxlength="2" class="md:col-span-1" />
            </div>
        </section>

        <section class="card card-corpo">
            <label class="campo"><span>Observações</span><textarea name="observacoes" rows="3" class="input">{{ old('observacoes', $cliente->observacoes) }}</textarea></label>
        </section>

        <div class="flex gap-3">
            <button class="btn-primario px-6">{{ $voltarPlaca ? 'Salvar e seguir para o carro' : 'Salvar cliente' }}</button>
            <a href="{{ $cliente->exists ? route('clientes.show', $cliente) : route('clientes.index') }}" class="btn">Cancelar</a>
        </div>
    </form>
</x-app>
