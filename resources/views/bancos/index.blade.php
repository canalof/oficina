<x-app titulo="Contas bancárias">
    <div class="mb-5">
        <h1>Contas bancárias</h1>
        <p class="text-suave">Gerencie contas, configure o Pix manual e concilie extratos.</p>
    </div>
    @if ($errors->any())
        <p class="mb-4 rounded-md border border-linha bg-white p-3 text-erro" role="alert">{{ $errors->first() }}</p>
    @endif

    @can('financeiro.editar')
        <form method="post" action="{{ route('bancos.store') }}" class="card card-corpo mb-5">
            @csrf
            <h2 class="mb-4">Cadastrar conta</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <label class="campo"><span>Apelido</span><input class="input" name="apelido" value="{{ old('apelido') }}" placeholder="Ex.: Conta principal" required maxlength="100"></label>
                <label class="campo"><span>Banco</span><input class="input" name="banco_nome" value="{{ old('banco_nome') }}" required maxlength="100"></label>
                <label class="campo"><span>Código do banco</span><input class="input" name="banco_codigo" value="{{ old('banco_codigo') }}" maxlength="10"></label>
                <label class="campo"><span>Agência</span><input class="input" name="agencia" value="{{ old('agencia') }}" maxlength="20"></label>
                <label class="campo"><span>Número da conta</span><input class="input" name="numero_conta" maxlength="40"></label>
                <label class="campo"><span>Tipo de conta</span>
                    <select class="input" name="tipo">
                        @foreach (['corrente' => 'Corrente', 'poupanca' => 'Poupança', 'pagamento' => 'Pagamento'] as $tipo => $rotulo)
                            <option value="{{ $tipo }}" @selected(old('tipo') === $tipo)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <button class="btn-primario mt-4">Cadastrar conta</button>
        </form>
    @endcan

    <h2 class="mb-3">Contas cadastradas</h2>
    @forelse ($contas as $conta)
        <section class="card card-corpo mb-4 space-y-4">
            <div>
                <h3>{{ $conta->apelido }}</h3>
                <p class="text-sm text-suave">{{ $conta->banco_nome }} · {{ $conta->pix_tipo ? 'Pix manual configurado' : 'Pix manual disponível para configuração' }}</p>
            </div>
            @can('financeiro.editar')
                <details class="rounded-md border border-linha p-3">
                    <summary class="cursor-pointer font-semibold">Configurar Pix manual</summary>
                    <form method="post" action="{{ route('bancos.pix', $conta) }}" class="mt-3">
                        @csrf @method('PUT')
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="campo"><span>Tipo de chave</span>
                                <select class="input" name="pix_tipo" required>
                                    @foreach (['cpf' => 'CPF', 'cnpj' => 'CNPJ', 'telefone' => 'Celular', 'email' => 'E-mail', 'aleatoria' => 'Aleatória'] as $tipo => $rotulo)
                                        <option value="{{ $tipo }}" @selected($conta->pix_tipo === $tipo)>{{ $rotulo }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="campo"><span>Chave Pix</span><input class="input" name="pix_chave" required maxlength="77" autocomplete="off" placeholder="Celular: +55…"></label>
                            <label class="campo"><span>Nome do recebedor</span><input class="input" name="pix_recebedor" required maxlength="25" value="{{ $conta->pix_recebedor }}"></label>
                            <label class="campo"><span>Cidade</span><input class="input" name="pix_cidade" required maxlength="15" value="{{ $conta->pix_cidade }}"></label>
                        </div>
                        <button class="btn-primario btn-sm mt-3">Salvar Pix manual</button>
                    </form>
                </details>
                <details class="rounded-md border border-linha p-3">
                    <summary class="cursor-pointer font-semibold">Registrar movimento</summary>
                    <form method="post" action="{{ route('bancos.movimento', $conta) }}" class="mt-3">
                        @csrf
                        <div class="grid gap-4 sm:grid-cols-3">
                            <label class="campo"><span>Data</span><input class="input" type="date" name="data_movimento" required></label>
                            <label class="campo"><span>Valor em centavos (débito negativo)</span><input class="input" type="number" name="valor_centavos" required></label>
                            <label class="campo"><span>Descrição</span><input class="input" name="descricao" required maxlength="255"></label>
                        </div>
                        <button class="btn-primario btn-sm mt-3">Registrar movimento</button>
                    </form>
                </details>
                <details class="rounded-md border border-linha p-3">
                    <summary class="cursor-pointer font-semibold">Importar extrato</summary>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <form method="post" enctype="multipart/form-data" action="{{ route('bancos.importar.ofx', $conta) }}">
                            @csrf
                            <label class="campo"><span>Arquivo OFX</span><input class="input" type="file" name="arquivo" accept=".ofx,.xml,.txt" required></label>
                            <button class="btn btn-sm mt-3">Importar OFX</button>
                        </form>
                        <form method="post" enctype="multipart/form-data" action="{{ route('bancos.importar', $conta) }}">
                            @csrf
                            <label class="campo"><span>Arquivo CSV</span><input class="input" type="file" name="arquivo" accept=".csv,.txt" required></label>
                            <p class="mt-1 text-sm text-suave">Colunas: data;descricao;valor_centavos;referencia</p>
                            <button class="btn btn-sm mt-3">Importar CSV</button>
                        </form>
                    </div>
                </details>
            @endcan
        </section>
    @empty
        <p class="card card-corpo mb-5 text-suave">Nenhuma conta bancária cadastrada.</p>
    @endforelse

    <h2 class="mb-3 mt-5">Últimos movimentos</h2>
    <div class="card overflow-x-auto">
        <table class="tabela">
            <thead><tr><th>Data</th><th>Conta</th><th>Descrição</th><th>Valor</th><th>Origem</th><th>Conciliação</th></tr></thead>
            <tbody>
                @forelse ($movimentos as $m)
                    <tr>
                        <td class="whitespace-nowrap">{{ $m->data_movimento?->format('d/m/Y') }}</td>
                        <td>{{ $m->contaBancaria?->apelido }}</td>
                        <td>{{ $m->descricao }}</td>
                        <td class="whitespace-nowrap">{{ \App\Support\Dinheiro::formatar($m->valor_centavos) }}</td>
                        <td>{{ $m->origem }}</td>
                        <td>
                            @if ($m->status_conciliacao === 'conciliado')
                                <p>Conciliado (título #{{ $m->conta_receber_id ?? $m->conta_pagar_id }})</p>
                                @can('financeiro.editar')
                                    <form method="post" action="{{ route('bancos.desfazer', $m) }}">@csrf
                                        <button class="btn btn-sm mt-2">Desfazer vínculo</button>
                                    </form>
                                @endcan
                            @else
                                <p class="text-suave">Pendente</p>
                                @can('financeiro.editar')
                                    <details class="mt-2">
                                        <summary class="cursor-pointer text-sm">Conciliar</summary>
                                        <form method="post" action="{{ route('bancos.conciliar', $m) }}" class="mt-2 space-y-2">
                                            @csrf
                                            <input type="hidden" name="tipo" value="{{ $m->valor_centavos > 0 ? 'receber' : 'pagar' }}">
                                            @if (($sugestoes[$m->id] ?? collect())->isNotEmpty())
                                                <label class="campo"><span>Possíveis títulos</span>
                                                    <select class="input" onchange="this.form.querySelector('[name=titulo_id]').value=this.value">
                                                        <option value="">Selecionar sugestão</option>
                                                        @foreach ($sugestoes[$m->id] as $titulo)
                                                            <option value="{{ $titulo->id }}">#{{ $titulo->id }} — {{ $titulo->descricao }} — {{ \App\Support\Dinheiro::formatar($titulo->valor_centavos) }}</option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                            @endif
                                            <label class="campo"><span>ID do título já liquidado</span><input class="input" type="number" min="1" name="titulo_id" required></label>
                                            <button class="btn btn-sm">Conciliar</button>
                                        </form>
                                    </details>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-suave">Nenhum movimento registrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app>
