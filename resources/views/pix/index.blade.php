<x-app titulo="Cobranças Pix">
    <div class="mb-5">
        <h1>Cobranças Pix</h1>
        <p class="text-suave">Gere o QR Code estático e confirme o recebimento após conferir o crédito no banco.</p>
    </div>
    @if ($errors->any())
        <p class="mb-4 rounded-md border border-linha bg-white p-3 text-erro" role="alert">{{ $errors->first() }}</p>
    @endif

    @can('financeiro.editar')
        <form method="post" action="{{ route('pix.store') }}" class="card card-corpo mb-5">
            @csrf
            <h2 class="mb-3">Preparar cobrança</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="campo"><span>Conta bancária</span>
                    <select class="input" name="conta_bancaria_id" required>
                        <option value="">Selecione uma conta</option>
                        @foreach ($contas as $conta)
                            <option value="{{ $conta->id }}">{{ $conta->apelido }}{{ $conta->pix_tipo ? '' : ' — sem chave Pix' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="campo"><span>Conta a receber</span>
                    <select class="input" name="conta_receber_id" required>
                        <option value="">Selecione um título</option>
                        @foreach ($titulos as $titulo)
                            <option value="{{ $titulo->id }}">{{ $titulo->descricao }} — {{ \App\Support\Dinheiro::formatar($titulo->valor_centavos) }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button class="btn-primario" @disabled($contas->isEmpty() || $titulos->isEmpty())>Preparar cobrança</button>
                <a class="btn" href="{{ route('bancos.index') }}">Configurar chave em Bancos</a>
            </div>
            @if ($contas->isEmpty() || $titulos->isEmpty())
                <p class="mt-3 text-sm text-suave">Você precisa de uma conta bancária ativa e de um título em aberto para preparar a cobrança.</p>
            @endif
        </form>
    @endcan

    <div class="card overflow-x-auto">
        <table class="tabela">
            <thead><tr><th>ID</th><th>Tipo</th><th>Valor</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody>
                @forelse ($cobrancas as $c)
                    <tr>
                        <td>#{{ $c->id }}</td>
                        <td>{{ ['pix_manual' => 'Pix manual', 'aguardando_integracao' => 'Rascunho'][$c->provedor] ?? $c->provedor }}</td>
                        <td class="whitespace-nowrap font-semibold">{{ \App\Support\Dinheiro::formatar($c->valor_centavos) }}</td>
                        <td><span class="rotulo border-linha">{{ ['pendente' => 'Pendente', 'confirmado' => 'Confirmado', 'cancelada' => 'Cancelada'][$c->status] ?? $c->status }}</span></td>
                        <td class="space-y-2">
                            @if ($c->pixManualDisponivel())
                                <details>
                                    <summary class="cursor-pointer font-semibold">Ver QR Code e Pix Copia e Cola</summary>
                                    <canvas class="my-3 bg-white p-3" data-qrcode="{{ $c->pix_payload }}" aria-label="QR Code Pix"></canvas>
                                    <label class="campo"><span>Pix Copia e Cola</span><textarea class="input" readonly rows="3">{{ $c->pix_payload }}</textarea></label>
                                </details>
                                @can('caixa.operar')
                                    <form method="post" action="{{ route('pix.confirmar-manual', $c->id) }}" data-confirmar="Você conferiu o crédito deste valor na conta bancária?">
                                        @csrf
                                        <button class="btn-primario btn-sm">Confirmar recebimento</button>
                                    </form>
                                @endcan
                            @endif
                            @can('financeiro.editar')
                                @if (in_array($c->provedor, ['aguardando_integracao', 'pix_manual'], true) && $c->status === 'pendente' && !$c->id_externo && !$c->txid && !$c->confirmado_em)
                                    <form method="post" action="{{ route('pix.cancelar-rascunho', $c->id) }}" data-confirmar="Cancelar no TORQUE? Um QR Code já compartilhado ainda pode ser pago no banco.">
                                        @csrf
                                        <button class="btn-perigo btn-sm">Cancelar preparação</button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-suave">Nenhuma cobrança Pix preparada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app>
