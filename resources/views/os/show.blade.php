@php
    use App\Support\StatusOs;
    use App\Support\Dinheiro;
    use App\Support\Checklist;
    $st = $os->status;
    $etapas = [StatusOs::Orcamento, StatusOs::Aguardando, StatusOs::Aprovada, StatusOs::EmServico, StatusOs::Pronta, StatusOs::Entregue];
    $posicao = array_search($st, $etapas, true);
    $pendentes = $os->itens->where('situacao', 'pendente');
    $podeEditar = auth()->user()->can('os.editar');
    $podeExecutar = auth()->user()->can('os.executar');
    $whats = $os->cliente?->aceita_whatsapp ? $os->cliente->linkWhatsapp() : null;
    $avarias = Checklist::avarias($os->checklist);
@endphp
<x-app :titulo="'OS '.$os->numeroFormatado()">
    {{-- Cabeçalho --}}
    <div class="mb-5 flex flex-wrap items-start gap-4">
        <x-placa :placa="$os->veiculo->placa" tamanho="md" />
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-3">
                <h1>OS {{ $os->numeroFormatado() }}</h1>
                <span class="rotulo {{ $st->cor() }}">{{ $st->rotulo() }}</span>
            </div>
            <p class="text-suave">
                <a href="{{ route('veiculos.show', $os->veiculo) }}" class="hover:underline">{{ $os->veiculo->descricao() ?: 'Modelo não informado' }}</a>
                @if ($os->cliente) · <a href="{{ route('clientes.show', $os->cliente) }}" class="hover:underline">{{ $os->cliente->nome }}</a>@endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('os.imprimir', $os) }}" target="_blank" class="btn">Imprimir</a>
            @if ($podeEditar && $st->noPatio())<a href="{{ route('os.edit', $os) }}" class="btn">Editar entrada</a>@endif
        </div>
    </div>

    {{-- Trilha de etapas --}}
    @if ($st !== StatusOs::Cancelada)
        <ol class="mb-6 grid grid-cols-6 gap-1 text-xs sm:text-sm" aria-label="Etapas da OS">
            @foreach ($etapas as $i => $e)
                <li class="min-w-0" @if($i === $posicao) aria-current="step" @endif>
                    <div @class(['h-1.5 rounded-full', 'bg-asfalto' => $i < $posicao, 'bg-sinal' => $i === $posicao, 'bg-linha' => $i > $posicao])></div>
                    <div @class(['mt-1.5 truncate', 'font-semibold text-texto' => $i === $posicao, 'text-suave' => $i !== $posicao])>{{ $e === StatusOs::Pronta ? 'Pronta' : $e->rotulo() }}</div>
                </li>
            @endforeach
        </ol>
    @else
        <div class="mb-6 rounded-md border border-[#ecc3bf] bg-[#fcf0ef] px-4 py-3 text-[15px] text-[#8c241d]">
            Cancelada em {{ $os->cancelado_em?->format('d/m/Y H:i') }}. Motivo: {{ $os->motivo_cancelamento }}
        </div>
    @endif

    {{-- Próximo passo --}}
    @if ($st->noPatio())
        <section class="card mb-6 border-l-4 border-l-sinal">
            <div class="flex flex-wrap items-center gap-4 px-5 py-4">
                <div class="min-w-0 flex-1">
                    <div class="text-sm text-suave">Próximo passo</div>
                    <div class="text-lg font-semibold">
                        @switch($st)
                            @case(StatusOs::Orcamento) {{ $os->itens->isEmpty() ? 'Montar o orçamento' : 'Enviar o orçamento para o cliente aprovar' }} @break
                            @case(StatusOs::Aguardando) Esperando o cliente aprovar @break
                            @case(StatusOs::Aprovada) {{ $pendentes->count() ? 'Itens adicionais esperando aprovação' : 'Começar o serviço' }} @break
                            @case(StatusOs::EmServico) {{ $pendentes->count() ? 'Itens adicionais esperando aprovação' : 'Terminar o serviço e marcar como pronto' }} @break
                            @case(StatusOs::Pronta) {{ $os->recebido_em || ! $os->total_centavos ? 'Entregar o carro ao cliente' : 'Receber o pagamento e entregar' }} @break
                        @endswitch
                    </div>
                    @if ($st === StatusOs::Aguardando && $os->enviado_em)
                        <div class="text-sm text-suave">Enviado {{ $os->enviado_em->diffForHumans() }}</div>
                    @endif
                    @if ($pendentes->isNotEmpty() && $os->token_expira_em)
                        <div @class(['text-sm', 'text-erro font-semibold' => ! $os->linkValido(), 'text-suave' => $os->linkValido()])>{{ $os->linkValido() ? 'Link do cliente válido até '.$os->token_expira_em->format('d/m/Y') : 'O link do cliente expirou. Gere um novo.' }}</div>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($podeEditar && $os->itens->isNotEmpty() && $pendentes->isNotEmpty() && in_array($st, [StatusOs::Orcamento, StatusOs::Aguardando, StatusOs::Aprovada, StatusOs::EmServico], true))
                        @if ($whats)
                            <form method="post" action="{{ route('os.acao', [$os, 'enviar']) }}" target="_blank">
                                @csrf <input type="hidden" name="whatsapp" value="1">
                                <button class="btn-primario" data-recarregar>{{ $os->enviado_em ? 'Reenviar pelo WhatsApp' : 'Enviar pelo WhatsApp' }}</button>
                            </form>
                        @else
                            <form method="post" action="{{ route('os.acao', [$os, 'enviar']) }}">
                                @csrf <button class="btn-primario">Gerar link para o cliente</button>
                            </form>
                        @endif
                        <button type="button" class="btn" data-copiar="{{ $os->linkAprovacao() }}">Copiar link</button>
                        <form method="post" action="{{ route('os.acao', [$os, 'novo-link']) }}" data-confirmar="Gerar um link novo? O link enviado antes deixa de funcionar.">@csrf<button class="btn btn-sm text-suave" title="Desativa o link anterior">Novo link</button></form>
                    @endif
                    @if ($st === StatusOs::Aprovada && $pendentes->isEmpty() && $podeExecutar)
                        <form method="post" action="{{ route('os.acao', [$os, 'iniciar']) }}">@csrf<button class="btn-primario">Iniciar serviço</button></form>
                    @endif
                    @if (in_array($st, [StatusOs::Aprovada, StatusOs::EmServico], true) && $pendentes->isEmpty() && $podeExecutar)
                        <form method="post" action="{{ route('os.acao', [$os, 'concluir']) }}">@csrf<button @class(['btn-primario' => $st === StatusOs::EmServico, 'btn' => $st !== StatusOs::EmServico])>Marcar como pronto</button></form>
                    @endif
                    @if ($st === StatusOs::Pronta && ! $os->recebido_em && $os->total_centavos > 0 && auth()->user()->can('caixa.operar'))
                        <a href="#pagamento" class="btn-primario">Receber e entregar</a>
                    @endif
                    @if ($st === StatusOs::Pronta && $podeEditar && ($os->recebido_em || $os->total_centavos === 0))
                        <form method="post" action="{{ route('os.acao', [$os, 'entregar']) }}" data-confirmar="Confirmar a entrega do carro? A garantia começa a contar hoje.">@csrf<button class="btn-primario">Entregar carro</button></form>
                    @endif
                    @if ($st === StatusOs::Pronta && $podeEditar)
                        @if ($whats)<a href="{{ $os->cliente->linkWhatsapp('Olá, '.explode(' ', $os->cliente->nome)[0].'! Seu '.$os->veiculo->descricao().' ('.$os->veiculo->placaFormatada().') está pronto para retirar. Total: '.$os->totalFormatado().'. '.auth()->user()->empresa->nome_fantasia) }}" target="_blank" rel="noopener" class="btn">Avisar pelo WhatsApp</a>@endif
                    @endif
                </div>
            </div>
        </section>
    @elseif ($st === StatusOs::Entregue)
        <section class="card card-corpo mb-6 text-[15px]">
            Entregue em <strong>{{ $os->entregue_em->format('d/m/Y H:i') }}</strong>.
            @if ($os->garantiaAte()) Garantia de {{ $os->garantia_dias }} dias, até <strong>{{ $os->garantiaAte()->format('d/m/Y') }}</strong>. @endif
        </section>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1.6fr_1fr]">
        <div class="min-w-0 space-y-6">
            {{-- Itens --}}
            <section class="card" id="itens">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-linha px-5 py-4">
                    <h2>Serviços e peças</h2>
                    @if ($pendentes->isNotEmpty() && $podeEditar)
                        <span class="text-sm text-suave">Marque o que o cliente aprovou por telefone ou no balcão</span>
                    @endif
                </div>

                @if ($os->itens->isEmpty())
                    <p class="px-5 py-8 text-center text-suave">Nenhum item ainda. Adicione abaixo os serviços e peças do orçamento.</p>
                @else
                    <form method="post" action="{{ route('os.acao', [$os, 'aprovar']) }}" id="form-aprovar">@csrf</form>
                    <div class="overflow-x-auto">
                        <table class="tabela">
                            <thead><tr>
                                @if ($pendentes->isNotEmpty() && $podeEditar)<th class="w-10"><span class="sr-only">Aprovar</span></th>@endif
                                <th>Descrição</th><th class="text-right">Qtd</th><th class="hidden text-right sm:table-cell">Unitário</th><th class="text-right">Total</th><th class="w-10"><span class="sr-only">Remover</span></th>
                            </tr></thead>
                            <tbody>
                            @foreach ($os->itens as $item)
                                <tr @class(['opacity-55' => $item->situacao === 'recusado'])>
                                    @if ($pendentes->isNotEmpty() && $podeEditar)
                                        <td>@if ($item->situacao === 'pendente')<input type="checkbox" name="itens[]" value="{{ $item->id }}" form="form-aprovar" class="h-5 w-5 accent-asfalto" checked aria-label="Aprovar {{ $item->descricao }}">@endif</td>
                                    @endif
                                    <td>
                                        <div @class(['font-medium', 'line-through' => $item->situacao === 'recusado'])>{{ $item->descricao }}</div>
                                        <div class="text-xs text-suave">
                                            {{ $item->tipo === 'peca' ? 'Peça' : 'Serviço' }}
                                            · @if($item->situacao === 'aprovado')<span class="text-ok">aprovado</span>@elseif($item->situacao === 'recusado')<span class="text-erro">recusado pelo cliente</span>@else<span class="text-sinal-escuro">aguardando aprovação</span>@endif
                                        </div>
                                    </td>
                                    <td class="text-right">{{ $item->quantidadeFormatada() }}</td>
                                    <td class="hidden whitespace-nowrap text-right sm:table-cell">{{ Dinheiro::formatar($item->valor_unitario_centavos) }}</td>
                                    <td class="whitespace-nowrap text-right font-semibold">{{ Dinheiro::formatar($item->total_centavos) }}</td>
                                    <td class="text-right">
                                        @if ($podeEditar && $st->aceitaItens() && ($item->situacao !== 'aprovado' || auth()->user()->can('os.cancelar')))
                                            <form method="post" action="{{ route('os.itens.destroy', [$os, $item]) }}" data-confirmar="Remover {{ $item->descricao }}?">
                                                @csrf @method('delete')
                                                <button class="rounded p-1 text-suave hover:bg-[#fcf0ef] hover:text-erro" aria-label="Remover {{ $item->descricao }}">
                                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap items-end justify-between gap-4 border-t border-linha px-5 py-4">
                        <div>
                            @if ($pendentes->isNotEmpty() && $podeEditar)
                                <button form="form-aprovar" class="btn btn-sm">Registrar aprovação dos marcados</button>
                                <p class="mt-1 text-xs text-suave">Os não marcados ficam como recusados.</p>
                            @endif
                        </div>
                        <dl class="min-w-[220px] space-y-1 text-[15px]">
                            <div class="flex justify-between gap-6"><dt class="text-suave">Subtotal</dt><dd>{{ Dinheiro::formatar($os->subtotal()) }}</dd></div>
                            @if ($os->desconto_centavos || ($podeEditar && $st->noPatio()))
                                <div class="flex items-center justify-between gap-6">
                                    <dt class="text-suave">Desconto</dt>
                                    <dd>
                                        @if ($podeEditar && $st->noPatio())
                                            <form method="post" action="{{ route('os.desconto', $os) }}" class="flex items-center gap-1">
                                                @csrf @method('put')
                                                <label class="sr-only" for="desconto">Desconto em reais</label>
                                                <input id="desconto" name="desconto" value="{{ Dinheiro::campo($os->desconto_centavos) }}" data-mascara="dinheiro" inputmode="decimal" class="input w-24 px-2 py-1 text-right text-sm">
                                                <button class="btn btn-sm px-2">OK</button>
                                            </form>
                                        @else
                                            − {{ Dinheiro::formatar($os->desconto_centavos) }}
                                        @endif
                                    </dd>
                                </div>
                            @endif
                            <div class="flex justify-between gap-6 border-t border-linha pt-1 text-lg font-semibold"><dt>Total</dt><dd>{{ $os->totalFormatado() }}</dd></div>
                        </dl>
                    </div>
                @endif

                @if ($podeEditar && $st->aceitaItens())
                    <form method="post" action="{{ route('os.itens.store', $os) }}" class="border-t border-linha bg-[#fafbfc] px-5 py-4" data-item-form>
                        @csrf
                        <div class="mb-3 flex flex-wrap items-center gap-3">
                            <span class="text-sm font-semibold">Adicionar</span>
                            <div class="inline-flex overflow-hidden rounded-md border border-[#c9cfd6] text-sm">
                                @foreach (['servico' => 'Serviço', 'peca' => 'Peça'] as $v => $r)
                                    <label class="cursor-pointer border-r border-[#c9cfd6] last:border-r-0">
                                        <input type="radio" name="tipo" value="{{ $v }}" class="peer sr-only" @checked(old('tipo', 'servico') === $v)>
                                        <span class="block bg-white px-3 py-1.5 font-medium peer-checked:bg-asfalto peer-checked:text-white peer-focus-visible:outline-3 peer-focus-visible:outline-sinal">{{ $r }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @if ($st !== StatusOs::Orcamento && $st !== StatusOs::Aguardando)
                                <span class="text-xs text-suave">Itens novos precisam de aprovação do cliente</span>
                            @endif
                        </div>
                        <label class="campo mb-3 max-w-md" data-so-servico><span>Do catálogo</span>
                            <select name="servico_id" class="input" data-catalogo>
                                <option value="">Escrever à mão</option>
                                @foreach ($servicos as $s)
                                    <option value="{{ $s->id }}" data-preco="{{ Dinheiro::campo($s->preco_centavos) }}" data-nome="{{ $s->nome }}">{{ $s->nome }} · {{ Dinheiro::formatar($s->preco_centavos) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="campo mb-3 max-w-md" data-so-peca hidden><span>Do estoque</span>
                            <select name="produto_id" class="input" data-estoque>
                                <option value="">Peça fora do estoque (escrever à mão)</option>
                                @foreach ($produtos as $p)
                                    <option value="{{ $p->id }}" data-preco="{{ Dinheiro::campo($p->preco_centavos) }}" data-nome="{{ $p->nome }}">{{ $p->nome }} · {{ Dinheiro::formatar($p->preco_centavos) }} · disponível {{ \App\Models\Produto::formatarQtd($p->disponivel()) }} {{ $p->unidade }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="grid gap-3 sm:grid-cols-[1fr_80px_130px_auto] sm:items-end">
                            <label class="campo"><span>Descrição</span>
                                <input name="descricao" value="{{ old('descricao') }}" class="input @error('descricao') input-erro @enderror" placeholder="Ex.: Pastilha de freio dianteira" maxlength="200">
                            </label>
                            <label class="campo"><span>Qtd</span>
                                <input name="quantidade" value="{{ old('quantidade', '1') }}" class="input text-right" inputmode="decimal">
                            </label>
                            <label class="campo"><span>Valor unitário</span>
                                <input name="valor" value="{{ old('valor') }}" class="input text-right @error('valor') input-erro @enderror" data-mascara="dinheiro" inputmode="decimal" placeholder="0,00">
                            </label>
                            <button class="btn-primario">Adicionar</button>
                        </div>
                        @foreach (['descricao', 'quantidade', 'valor', 'tipo'] as $campo)
                            @error($campo)<p class="erro-msg">{{ $message }}</p>@enderror
                        @endforeach
                        @if ($servicos->isEmpty() && auth()->user()->can('servicos.gerenciar'))
                            <p class="mt-2 text-xs text-suave">Dica: cadastre os serviços mais comuns no <a href="{{ route('servicos.index') }}" class="underline">catálogo</a> para montar orçamentos mais rápido.</p>
                        @endif
                    </form>
                @endif
            </section>

            {{-- Pagamento --}}
            @if ($st === StatusOs::Pronta && ! $os->recebido_em && $os->total_centavos > 0 && auth()->user()->can('caixa.operar'))
                @php $formas = \App\Services\Financeiro\Financeiro::FORMAS; @endphp
                <section class="card border-2 border-asfalto" id="pagamento">
                    <div class="border-b border-linha px-5 py-4">
                        <h2>Receber {{ $os->totalFormatado() }}</h2>
                        <p class="text-sm text-suave">Pode dividir em até 3 formas. Boleto e "a prazo" viram contas a receber.@unless($caixaAberto) <strong>O caixa está fechado:</strong> dinheiro só depois de abrir.@endunless</p>
                    </div>
                    <form method="post" action="{{ route('os.receber', $os) }}" class="space-y-3 px-5 py-4" data-pagamento="{{ $os->total_centavos }}">
                        @csrf
                        @for ($i = 0; $i < 3; $i++)
                            <div class="grid items-end gap-2 sm:grid-cols-[1.3fr_1fr_90px_1.2fr]" data-linha @if($i > 0 && ! old('pagamentos.'.$i.'.valor')) hidden @endif>
                                <label class="campo"><span>Forma</span>
                                    <select name="pagamentos[{{ $i }}][forma]" class="input" data-forma>
                                        @foreach ($formas as $v => $r)@continue($v === 'transferencia')<option value="{{ $v }}" @selected(old('pagamentos.'.$i.'.forma', $i === 0 ? 'pix' : 'dinheiro') === $v)>{{ $r }}</option>@endforeach
                                    </select>
                                </label>
                                <label class="campo"><span>Valor</span><input name="pagamentos[{{ $i }}][valor]" class="input text-right" data-mascara="dinheiro" inputmode="decimal" value="{{ old('pagamentos.'.$i.'.valor', $i === 0 ? Dinheiro::campo($os->total_centavos) : '') }}" data-valor></label>
                                <label class="campo" data-parcelas><span>Parcelas</span><input name="pagamentos[{{ $i }}][parcelas]" type="number" min="1" max="24" value="{{ old('pagamentos.'.$i.'.parcelas', 1) }}" class="input"></label>
                                <label class="campo" data-vencimento><span>1º vencimento</span><input name="pagamentos[{{ $i }}][vencimento]" type="date" value="{{ old('pagamentos.'.$i.'.vencimento', today()->addDays(30)->toDateString()) }}" class="input"></label>
                            </div>
                        @endfor
                        <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                            <button type="button" class="text-sm font-semibold text-asfalto-3 hover:underline" data-mais-forma>+ dividir em outra forma</button>
                            <span class="text-sm" data-restante></span>
                        </div>
                        <label class="flex items-center gap-2 text-[15px]"><input type="checkbox" name="entregar" value="1" checked class="h-5 w-5 accent-asfalto"> Entregar o carro agora</label>
                        <button class="btn-primario px-6 py-3 text-base">Registrar pagamento</button>
                    </form>
                </section>
            @endif

            @if ($os->contasReceber->isNotEmpty())
                <section class="card card-corpo">
                    <h2 class="mb-3">Pagamento</h2>
                    <ul class="space-y-1 text-[15px]">
                        @foreach ($os->contasReceber as $cr)
                            <li class="flex flex-wrap justify-between gap-3">
                                <span>{{ \App\Services\Financeiro\Financeiro::FORMAS[$cr->forma] ?? $cr->forma }}{{ $cr->parcela ? ' · '.$cr->parcela : '' }}</span>
                                <span>{{ Dinheiro::formatar($cr->valor_centavos) }} · @if($cr->status === 'recebida')<span class="text-ok">recebido</span>@else<span @class(['text-erro' => $cr->vencida(), 'text-sinal-escuro' => ! $cr->vencida()])>vence {{ $cr->vencimento->format('d/m/Y') }}</span>@endif</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($os->aceites->isNotEmpty())
                <section class="card card-corpo">
                    <h2 class="mb-1">Aprovações registradas</h2>
                    <p class="mb-3 text-sm text-suave">Cópia do que foi aprovado em cada momento, com data, origem e versão do orçamento.</p>
                    <ul class="space-y-3 text-[15px]">
                        @foreach ($os->aceites as $a)
                            <li class="rounded-md border border-linha px-3 py-2">
                                <div class="flex flex-wrap justify-between gap-2">
                                    <span class="font-semibold">{{ $a->nome }}</span>
                                    <span class="text-sm text-suave">{{ $a->created_at->format('d/m/Y H:i') }} · {{ $a->canal === 'link' ? 'pelo link'.($a->ip ? ' · IP '.$a->ip : '') : 'no balcão/telefone' }} · versão {{ $a->versao }}</span>
                                </div>
                                <div class="mt-1 text-sm">
                                    @foreach ($a->itens as $i)
                                        <span @class(['mr-2 inline-block', 'text-ok' => $i['decisao'] === 'aprovado', 'text-suave line-through' => $i['decisao'] !== 'aprovado'])>{{ $i['descricao'] }} ({{ Dinheiro::formatar($i['total_centavos']) }})</span>
                                    @endforeach
                                </div>
                                @unless ($a->integro())<p class="mt-1 text-sm font-semibold text-erro">Atenção: este registro foi alterado fora do sistema.</p>@endunless
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @can('financeiro.ver')
                @php
                    $custoPecas = (int) $os->itens->where('situacao', 'aprovado')->where('tipo', 'peca')->sum(fn ($i) => (int) round((float) $i->quantidade * (int) ($i->produto?->custo_centavos ?? 0)));
                    $semCusto = $os->itens->where('situacao', 'aprovado')->where('tipo', 'peca')->contains(fn ($i) => ! $i->produto || ! $i->produto->custo_centavos);
                @endphp
                @if ($os->itens->where('situacao', 'aprovado')->isNotEmpty())
                    <section class="card card-corpo">
                        <h2 class="mb-2">Margem desta OS</h2>
                        <dl class="grid grid-cols-3 gap-3 text-[15px]">
                            <div><dt class="text-sm text-suave">Total</dt><dd class="font-semibold">{{ $os->totalFormatado() }}</dd></div>
                            <div><dt class="text-sm text-suave">Custo das peças</dt><dd class="font-semibold">{{ Dinheiro::formatar($custoPecas) }}</dd></div>
                            <div><dt class="text-sm text-suave">Margem bruta</dt><dd class="font-semibold">{{ Dinheiro::formatar($os->total_centavos - $custoPecas) }}@if($os->total_centavos) <span class="text-sm font-normal text-suave">({{ number_format(($os->total_centavos - $custoPecas) / $os->total_centavos * 100, 0) }}%)</span>@endif</dd></div>
                        </dl>
                        @if ($semCusto)<p class="mt-2 text-xs text-suave">Há peça sem custo cadastrado; a margem real é menor.</p>@endif
                    </section>
                @endif
            @endcan

            {{-- Notas fiscais --}}
            @if ($os->recebido_em && auth()->user()->can('notas.emitir') && $st !== StatusOs::Cancelada)
                @php $temServ = $os->itens->where('situacao', 'aprovado')->where('tipo', 'servico')->isNotEmpty(); @endphp
                @php $temPeca = $os->itens->where('situacao', 'aprovado')->where('tipo', 'peca')->isNotEmpty(); @endphp
                @php $ativa = fn ($t) => $os->notas->where('tipo', $t)->whereIn('status', ['processando', 'autorizada'])->isNotEmpty(); @endphp
                <section class="card" id="notas">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-linha px-5 py-4">
                        <h2>Notas fiscais</h2>
                        <div class="flex flex-wrap gap-2">
                            @if ($temServ && ! $ativa('nfse'))<form method="post" action="{{ route('notas.emitir', [$os, 'nfse']) }}">@csrf<button class="btn btn-sm">Emitir NFS-e (serviços)</button></form>@endif
                            @if ($temPeca && ! $ativa('nfce') && ! $ativa('nfe'))
                                <form method="post" action="{{ route('notas.emitir', [$os, 'nfce']) }}">@csrf<button class="btn btn-sm">Emitir NFC-e (peças)</button></form>
                                <form method="post" action="{{ route('notas.emitir', [$os, 'nfe']) }}">@csrf<button class="btn btn-sm" title="Para empresa que pede NF-e">NF-e (peças, empresa)</button></form>
                            @endif
                        </div>
                    </div>
                    @if ($os->notas->isEmpty())
                        <p class="px-5 py-5 text-sm text-suave">Nenhuma nota emitida.@if(! auth()->user()->empresa->fiscal_token) Sem token da Focus NFe, as notas saem em modo demonstração.@endif</p>
                    @else
                        <ul class="divide-y divide-linha">@include('notas._lista', ['notas' => $os->notas])</ul>
                    @endif
                </section>
            @endif

            {{-- Fotos --}}
            <section class="card" id="fotos">
                <div class="flex items-center justify-between border-b border-linha px-5 py-4">
                    <h2>Fotos ({{ $os->fotos->count() }})</h2>
                    <span class="text-sm text-suave">O cliente vê as fotos no link do orçamento</span>
                </div>
                @if ($os->fotos->isNotEmpty())
                    <div class="grid grid-cols-2 gap-3 p-5 sm:grid-cols-3">
                        @foreach ($os->fotos as $f)
                            <figure class="group relative overflow-hidden rounded-md border border-linha">
                                <a href="{{ route('os.fotos.show', [$os, $f]) }}" target="_blank"><img src="{{ route('os.fotos.show', [$os, $f]) }}" alt="{{ $f->legenda ?: 'Foto da OS' }}" loading="lazy" class="aspect-[4/3] w-full object-cover"></a>
                                @if ($f->legenda)<figcaption class="truncate px-2 py-1 text-xs text-suave">{{ $f->legenda }}</figcaption>@endif
                                @if ($podeExecutar && $st->noPatio())
                                    <form method="post" action="{{ route('os.fotos.destroy', [$os, $f]) }}" class="absolute right-1 top-1" data-confirmar="Remover esta foto?">
                                        @csrf @method('delete')
                                        <button class="rounded bg-white/90 p-1 text-erro shadow-sm" aria-label="Remover foto"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
                                    </form>
                                @endif
                            </figure>
                        @endforeach
                    </div>
                @endif
                @if ($podeExecutar && $st->noPatio())
                    <form method="post" action="{{ route('os.fotos.store', $os) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3 border-t border-linha px-5 py-4">
                        @csrf
                        <label class="campo min-w-[200px] flex-1"><span>Fotos (pode escolher várias)</span>
                            <input type="file" name="fotos[]" accept="image/*" capture="environment" multiple class="input py-1.5 text-sm file:mr-3 file:rounded file:border-0 file:bg-asfalto file:px-3 file:py-1 file:text-white">
                        </label>
                        <label class="campo min-w-[180px] flex-1"><span>Legenda (opcional)</span>
                            <input name="legenda" class="input" placeholder="Ex.: pastilha gasta" maxlength="160">
                        </label>
                        <button class="btn">Enviar fotos</button>
                        @error('fotos')<p class="erro-msg w-full">{{ $message }}</p>@enderror
                        @error('fotos.*')<p class="erro-msg w-full">{{ $message }}</p>@enderror
                    </form>
                @endif
            </section>
        </div>

        <aside class="min-w-0 space-y-6">
            <section class="card card-corpo">
                <h2 class="mb-3">Entrada</h2>
                <dl class="space-y-2 text-[15px]">
                    <div class="flex justify-between gap-4"><dt class="text-suave">Aberta em</dt><dd>{{ $os->created_at->format('d/m/Y H:i') }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-suave">Km</dt><dd>{{ $os->km_entrada ? number_format($os->km_entrada, 0, ',', '.') : '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-suave">Combustível</dt><dd>{{ $os->combustivel !== null ? Checklist::COMBUSTIVEL[$os->combustivel] : '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-suave">Previsão</dt><dd>{{ $os->previsao_entrega?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-suave">Atendido por</dt><dd class="truncate">{{ $os->consultor?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-suave">Garantia</dt><dd>{{ $os->garantia_dias }} dias</dd></div>
                </dl>
                @if ($os->defeito_relatado)
                    <h3 class="mt-4 text-sm font-semibold text-suave">Relato do cliente</h3>
                    <p class="mt-1 whitespace-pre-line text-[15px]">{{ $os->defeito_relatado }}</p>
                @endif
                @if ($os->checklist)
                    <h3 class="mt-4 text-sm font-semibold text-suave">Checklist</h3>
                    @if ($avarias)
                        <ul class="mt-1 space-y-1 text-[15px]">
                            @foreach ($avarias as $a)
                                <li><span class="font-medium text-erro">{{ $a['rotulo'] }}</span>{{ $a['obs'] ? ': '.$a['obs'] : '' }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-1 text-[15px]">Sem avarias registradas ({{ count($os->checklist) }} itens conferidos).</p>
                    @endif
                @endif
                @if ($os->observacoes)
                    <h3 class="mt-4 text-sm font-semibold text-suave">Observações internas</h3>
                    <p class="mt-1 whitespace-pre-line text-[15px]">{{ $os->observacoes }}</p>
                @endif
            </section>

            <section class="card card-corpo">
                <h2 class="mb-3">Diagnóstico</h2>
                @if ($podeExecutar && $st->noPatio())
                    <form method="post" action="{{ route('os.diagnostico', $os) }}" class="space-y-3">
                        @csrf @method('put')
                        <label class="campo"><span>Mecânico</span>
                            <select name="mecanico_id" class="input">
                                <option value="">Não definido</option>
                                @foreach ($mecanicos as $m)
                                    <option value="{{ $m->id }}" @selected($os->mecanico_id === $m->id)>{{ $m->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="campo"><span>O que foi encontrado</span>
                            <textarea name="diagnostico" rows="4" class="input" placeholder="Ex.: disco de freio empenado, pastilhas no limite">{{ old('diagnostico', $os->diagnostico) }}</textarea>
                        </label>
                        <button class="btn btn-sm">Salvar diagnóstico</button>
                    </form>
                @else
                    <p class="text-[15px]">Mecânico: {{ $os->mecanico?->name ?? '—' }}</p>
                    <p class="mt-2 whitespace-pre-line text-[15px]">{{ $os->diagnostico ?: 'Sem diagnóstico registrado.' }}</p>
                @endif
            </section>

            <section class="card card-corpo">
                <h2 class="mb-3">Histórico</h2>
                <ol class="space-y-3 text-[15px]">
                    @foreach ($os->eventos as $ev)
                        <li class="relative pl-4 before:absolute before:left-0 before:top-2 before:h-1.5 before:w-1.5 before:rounded-full before:bg-asfalto-3">
                            <div>{{ $ev->descricao }}</div>
                            <div class="text-xs text-suave">{{ $ev->created_at->format('d/m H:i') }}{{ $ev->user ? ' · '.$ev->user->name : '' }}</div>
                        </li>
                    @endforeach
                </ol>
            </section>

            @if ($outras->isNotEmpty())
                <section class="card card-corpo">
                    <h2 class="mb-3">Outras OS deste carro</h2>
                    <ul class="space-y-2 text-[15px]">
                        @foreach ($outras as $o)
                            <li class="flex justify-between gap-3">
                                <a href="{{ route('os.show', $o) }}" class="font-medium hover:underline">{{ $o->numeroFormatado() }} · {{ $o->created_at->format('d/m/Y') }}</a>
                                <span class="text-suave">{{ $o->status->rotulo() }}</span>
                            </li>
                            @php $recusados = $o->itens->where('situacao', 'recusado'); @endphp
                            @if ($recusados->isNotEmpty())
                                <li class="-mt-1 text-sm text-suave">Recusado: {{ $recusados->pluck('descricao')->join(', ') }}</li>
                            @endif
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($st === StatusOs::Entregue && auth()->user()->can('os.cancelar'))
                <details class="card card-corpo">
                    <summary class="cursor-pointer text-[15px] font-semibold">Reabrir OS</summary>
                    <p class="mt-2 text-sm text-suave">Volta para "Pronta", devolve as peças ao estoque e retira comissões ainda não pagas. O pagamento continua registrado.</p>
                    <form method="post" action="{{ route('os.acao', [$os, 'reabrir']) }}" class="mt-3 space-y-3" data-confirmar="Reabrir esta OS?">
                        @csrf
                        <label class="campo"><span>Motivo</span><input name="motivo" class="input" required maxlength="200"></label>
                        <button class="btn btn-sm">Reabrir OS</button>
                    </form>
                </details>
            @endif

            @if ($st->noPatio() && auth()->user()->can('os.cancelar'))
                <details class="card card-corpo">
                    <summary class="cursor-pointer text-[15px] font-semibold text-erro">Cancelar OS</summary>
                    <form method="post" action="{{ route('os.acao', [$os, 'cancelar']) }}" class="mt-3 space-y-3" data-confirmar="Cancelar esta OS?">
                        @csrf
                        <label class="campo"><span>Motivo</span><input name="motivo" class="input" required maxlength="255" placeholder="Ex.: cliente desistiu"></label>
                        <button class="btn-perigo btn-sm">Cancelar OS</button>
                    </form>
                </details>
            @endif
        </aside>
    </div>
</x-app>
