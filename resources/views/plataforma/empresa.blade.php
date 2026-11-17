@php use App\Support\Dinheiro; use App\Models\Empresa; @endphp
<x-app :titulo="$empresa->nome_fantasia">
    <h1 class="mb-1">{{ $empresa->nome_fantasia }}</h1>
    <p class="mb-4 text-suave">{{ $empresa->cnpj ? \App\Support\Documento::formatar($empresa->cnpj).' · ' : '' }}{{ $empresa->email }} · {{ $empresa->telefone }} · desde {{ $empresa->created_at->format('d/m/Y') }}</p>
    @include('plataforma._menu')
    <section class="card card-corpo mb-5">
        <h2 class="mb-4">Dados da oficina</h2>
        <form method="post" action="{{ route('plataforma.empresa.dados', $empresa) }}">
            @csrf @method('put')
            <div class="grid gap-4 sm:grid-cols-3">
                <x-campo nome="nome_fantasia" rotulo="Nome da oficina" :valor="$empresa->nome_fantasia" required maxlength="120" />
                <x-campo nome="email" rotulo="E-mail de contato" tipo="email" :valor="$empresa->email" maxlength="255" />
                <x-campo nome="telefone" rotulo="Telefone" :valor="$empresa->telefone" maxlength="20" />
            </div>
            <p class="mt-2 text-sm text-suave">O e-mail de contato é separado do login do responsável.</p>
            <button class="btn-primario mt-4">Salvar dados da oficina</button>
        </form>
    </section>
    <div class="mb-6 grid gap-4 sm:grid-cols-4">
        <div class="card card-corpo"><div class="text-sm text-suave">Usuários ativos</div><div class="mt-1 text-2xl font-semibold">{{ $uso['usuarios'] }}</div></div>
        <div class="card card-corpo"><div class="text-sm text-suave">OS no mês</div><div class="mt-1 text-2xl font-semibold">{{ $uso['os_mes'] }}</div></div>
        <div class="card card-corpo"><div class="text-sm text-suave">Consultas de placa no mês</div><div class="mt-1 text-2xl font-semibold">{{ $uso['placas_mes'] }}</div></div>
        <div class="card card-corpo"><div class="text-sm text-suave">Notas no mês</div><div class="mt-1 text-2xl font-semibold">{{ $uso['notas_mes'] }}</div></div>
    </div>
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card card-corpo">
            <h2 class="mb-4">Assinatura</h2>
            @if ($empresa->assinatura_status === Empresa::CADASTRO_PENDENTE)
                <p class="mb-4 text-suave">Cadastro aguardando liberação. Ao aprovar a oficina, você inicia o teste grátis de {{ Empresa::DIAS_TRIAL }} dias.</p>
                <form method="post" action="{{ route('plataforma.empresa.liberar', $empresa) }}" data-confirmar="Liberar o acesso desta oficina?">
                    @csrf
                    <button class="btn-primario">Liberar oficina</button>
                </form>
            @else
            <form method="post" action="{{ route('plataforma.empresa.update', $empresa) }}" class="space-y-4">
                @csrf @method('put')
                <label class="campo"><span>Plano</span><select name="plano_id" class="input">@foreach($planos as $p)<option value="{{ $p->id }}" @selected($empresa->plano_id === $p->id)>{{ $p->nome }} · {{ $p->precoFormatado() }}</option>@endforeach</select></label>
                <label class="campo"><span>Situação</span><select name="assinatura_status" class="input">@foreach(Empresa::STATUS as $k => $r)<option value="{{ $k }}" @selected($empresa->assinatura_status === $k)>{{ $r }}</option>@endforeach</select></label>
                <x-campo nome="trial_ate" rotulo="Teste até" tipo="date" :valor="$empresa->trial_ate?->toDateString()" ajuda="Para dar mais dias de teste" />
                <label class="flex items-center gap-2 text-[15px]"><input type="hidden" name="ativo" value="0"><input type="checkbox" name="ativo" value="1" class="h-4 w-4 accent-asfalto" @checked($empresa->ativo)> Acesso liberado (desmarcar derruba todos os usuários)</label>
                <button class="btn-primario">Salvar</button>
            </form>
            <p class="mt-4 text-sm text-suave">Gateway: cliente {{ $empresa->cobranca_cliente_id ?? '—' }} · assinatura {{ $empresa->cobranca_assinatura_id ?? '—' }}</p>
            @endif
        </section>
        <section class="card overflow-x-auto">
            <div class="border-b border-linha px-5 py-4"><h2>Faturas</h2></div>
            @if ($empresa->assinatura_status !== Empresa::CADASTRO_PENDENTE)
                <form method="post" action="{{ route('plataforma.empresa.cobrancas.store', $empresa) }}" class="card-corpo space-y-3">
                    @csrf
                    <h3>Registrar cobrança por link</h3>
                    <p class="text-sm text-suave">Crie a cobrança no seu banco ou provedor de pagamento e cole o link aqui. A oficina também verá a fatura em Assinatura.</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-campo nome="valor" rotulo="Valor (R$)" mascara="dinheiro" inputmode="decimal" required />
                        <x-campo nome="vencimento" rotulo="Vencimento" tipo="date" required />
                    </div>
                    @error('valor_centavos')<p class="erro-msg">{{ $message }}</p>@enderror
                    <x-campo nome="url" rotulo="Link de pagamento" tipo="url" placeholder="https://…" maxlength="500" required />
                    <button class="btn-primario">Registrar cobrança</button>
                </form>
            @endif
            @php $numero = \App\Services\WhatsApp\WhatsApp::numero($empresa->telefone); @endphp
            <table class="tabela"><thead><tr><th>Vencimento</th><th class="text-right">Valor</th><th>Situação</th><th>Ações</th></tr></thead><tbody>
                @forelse ($faturas as $f)
                    <tr>
                        <td>{{ $f->vencimento?->format('d/m/Y') }}</td>
                        <td class="whitespace-nowrap text-right">{{ Dinheiro::formatar($f->valor_centavos) }}</td>
                        <td>{{ ['pendente' => 'Aberta', 'paga' => 'Paga', 'vencida' => 'Vencida', 'estornada' => 'Estornada', 'cancelada' => 'Cancelada'][$f->status] ?? $f->status }}</td>
                        <td>
                            @if (in_array($f->status, ['pendente', 'vencida'], true))
                                <div class="flex flex-wrap gap-2">
                                    @if ($f->url && str_starts_with($f->url, 'https://'))
                                        <button type="button" class="btn btn-sm" data-copiar="{{ $f->url }}">Copiar link</button>
                                        @if ($numero)
                                            @php
                                                $texto = 'Olá! Segue a cobrança do TORQUE de '.Dinheiro::formatar($f->valor_centavos)
                                                    .($f->vencimento ? ', com vencimento em '.$f->vencimento->format('d/m/Y') : '').': '.$f->url;
                                            @endphp
                                            <a class="btn btn-sm" href="https://wa.me/{{ $numero }}?text={{ rawurlencode($texto) }}" target="_blank" rel="noopener">Abrir WhatsApp</a>
                                        @endif
                                    @endif
                                    @if ($f->manual())
                                        <form method="post" action="{{ route('plataforma.empresa.cobrancas.confirmar', [$empresa, $f->id]) }}" data-confirmar="Você já conferiu o recebimento no banco ou no provedor?">
                                            @csrf
                                            <button class="btn btn-sm">Registrar pagamento</button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-6 text-center text-suave">Sem faturas.</td></tr>
                @endforelse
            </tbody></table>
        </section>
    </div>
</x-app>
