<x-app titulo="Empresa">
    <h1 class="mb-1">Dados da empresa</h1>
    <p class="mb-5 text-suave">Aparecem nos orçamentos, nas ordens de serviço e nas notas fiscais.</p>
    <form method="post" action="{{ route('empresa.update') }}" class="max-w-3xl space-y-6">
        @csrf @method('put')
        <section class="card card-corpo">
            <h2 class="mb-4">Identificação e endereço</h2>
            <div class="grid gap-4 md:grid-cols-6">
                <x-campo nome="nome_fantasia" rotulo="Nome fantasia" :valor="$empresa->nome_fantasia" required class="md:col-span-3" />
                <x-campo nome="razao_social" rotulo="Razão social" :valor="$empresa->razao_social" class="md:col-span-3" />
                <x-campo nome="cnpj" rotulo="CNPJ" :valor="$empresa->cnpj ? \App\Support\Documento::formatar($empresa->cnpj) : null" mascara="documento" class="md:col-span-2" />
                <x-campo nome="telefone" rotulo="Telefone" :valor="$empresa->telefone" mascara="telefone" class="md:col-span-2" />
                <x-campo nome="email" rotulo="E-mail" tipo="email" :valor="$empresa->email" class="md:col-span-2" />
                <x-campo nome="cep" rotulo="CEP" :valor="$empresa->cep ? substr($empresa->cep, 0, 5).'-'.substr($empresa->cep, 5) : ''" mascara="cep" class="md:col-span-2" />
                <x-campo nome="logradouro" rotulo="Rua" :valor="$empresa->logradouro" class="md:col-span-3" />
                <x-campo nome="numero" rotulo="Número" :valor="$empresa->numero" class="md:col-span-1" />
                <x-campo nome="bairro" rotulo="Bairro" :valor="$empresa->bairro" class="md:col-span-2" />
                <x-campo nome="cidade" rotulo="Cidade" :valor="$empresa->cidade" class="md:col-span-3" />
                <x-campo nome="uf" rotulo="UF" :valor="$empresa->uf" maxlength="2" class="md:col-span-1" />
            </div>
        </section>

        <section class="card card-corpo">
            <h2 class="mb-1">Dados fiscais</h2>
            <p class="mb-4 text-sm text-suave">Usados para emitir NFS-e, NFC-e e NF-e. <strong>Confira com o seu contador</strong> antes de emitir em produção.</p>
            <div class="grid gap-4 md:grid-cols-6">
                <x-campo nome="inscricao_estadual" rotulo="Inscrição estadual" :valor="$empresa->inscricao_estadual" class="md:col-span-2" />
                <x-campo nome="inscricao_municipal" rotulo="Inscrição municipal" :valor="$empresa->inscricao_municipal" class="md:col-span-2" />
                <x-campo nome="codigo_municipio" rotulo="Código do município (IBGE)" :valor="$empresa->codigo_municipio" inputmode="numeric" maxlength="7" ajuda="7 dígitos. Sumaré/SP = 3552403" class="md:col-span-2" />
                <label class="campo md:col-span-2"><span>Regime tributário</span>
                    <select name="regime_tributario" class="input">
                        @foreach ([1 => 'Simples Nacional', 2 => 'Simples (excesso de sublimite)', 3 => 'Regime normal'] as $v => $r)
                            <option value="{{ $v }}" @selected((int) old('regime_tributario', $empresa->regime_tributario) === $v)>{{ $r }}</option>
                        @endforeach
                    </select>
                </label>
                <x-campo nome="cfop_padrao" rotulo="CFOP das peças" :valor="$empresa->cfop_padrao" maxlength="4" ajuda="Ex.: 5102 ou 5405 (peça com ST)" class="md:col-span-2" />
                <x-campo nome="csosn_padrao" rotulo="CSOSN (Simples)" :valor="$empresa->csosn_padrao" maxlength="3" ajuda="Ex.: 102 ou 500" class="md:col-span-2" />
                <x-campo nome="item_lista_servico" rotulo="Item da lista de serviço" :valor="$empresa->item_lista_servico" ajuda="Oficinas: normalmente 14.01" class="md:col-span-2" />
                <x-campo nome="aliquota_iss" rotulo="Alíquota do ISS (%)" :valor="rtrim(rtrim((string) $empresa->aliquota_iss, '0'), '.')" inputmode="decimal" class="md:col-span-2" />
                <label class="campo md:col-span-2"><span>Ambiente</span>
                    <select name="fiscal_ambiente" class="input">
                        <option value="homologacao" @selected($empresa->fiscal_ambiente === 'homologacao')>Homologação (teste)</option>
                        <option value="producao" @selected($empresa->fiscal_ambiente === 'producao')>Produção (vale de verdade)</option>
                    </select>
                </label>
                <label class="campo md:col-span-6"><span>Token da Focus NFe</span>
                    <input type="password" name="fiscal_token" class="input" autocomplete="off" placeholder="{{ $empresa->fiscal_token ? 'Token salvo. Digite para trocar.' : 'Sem token: as notas saem em modo demonstração' }}">
                </label>
                @if ($empresa->fiscal_token)
                    <label class="flex items-center gap-2 text-[15px] md:col-span-6"><input type="checkbox" name="remover_token" value="1" class="h-4 w-4 accent-asfalto"> Remover token (volta para demonstração)</label>
                @endif
            </div>
        </section>

        <section class="card card-corpo">
            <h2 class="mb-1">WhatsApp automático</h2>
            <p class="mb-4 text-sm text-suave">Opcional. Com a Evolution API configurada no servidor, lembretes de revisão e confirmações de agenda saem sozinhos. Sem isso, o sistema abre o WhatsApp com a mensagem pronta.</p>
            <x-campo nome="whatsapp_instancia" rotulo="Nome da instância na Evolution API" :valor="$empresa->whatsapp_instancia" class="max-w-sm" />
            @if (! config('whatsapp.url'))<p class="mt-2 text-sm text-suave">O servidor ainda não tem EVOLUTION_URL e EVOLUTION_KEY no .env.</p>@endif
        </section>

        <button class="btn-primario">Salvar dados da empresa</button>
    </form>
</x-app>
