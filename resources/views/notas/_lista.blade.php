@php use App\Support\Dinheiro; @endphp
@foreach ($notas as $n)
    <li class="px-5 py-3">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <span class="font-semibold">{{ $n->tipoRotulo() }}</span>
            @if ($n->numero)<span class="text-sm">nº {{ $n->numero }}</span>@endif
            <span class="rotulo {{ $n->statusCor() }}">{{ $n->statusRotulo() }}</span>
            @if ($n->demonstracao())<span class="rotulo border-linha bg-[#f3f4f6] text-suave">Demonstração</span>@elseif($n->ambiente === 'homologacao')<span class="rotulo border-linha bg-[#f3f4f6] text-suave">Teste</span>@endif
            @isset($mostrarOs)<a href="{{ route('os.show', $n->ordem_servico_id) }}" class="text-sm hover:underline">OS {{ $n->ordemServico?->numeroFormatado() }} · {{ $n->ordemServico?->cliente?->nome }}</a>@endisset
            <span class="ml-auto font-semibold">{{ Dinheiro::formatar($n->valor_centavos) }}</span>
        </div>
        <div class="mt-0.5 text-xs text-suave">{{ $n->created_at->format('d/m/Y H:i') }}</div>
        @if ($n->mensagem && $n->status !== 'autorizada')<p @class(['mt-1 text-sm', 'text-erro' => $n->status === 'erro', 'text-suave' => $n->status !== 'erro'])>{{ $n->mensagem }}</p>@endif
        <div class="mt-2 flex flex-wrap gap-2">
            @if ($n->url_pdf)<a href="{{ $n->url_pdf }}" target="_blank" rel="noopener" class="btn btn-sm">Ver nota (PDF)</a>@endif
            @if ($n->url_xml)<a href="{{ $n->url_xml }}" target="_blank" rel="noopener" class="btn btn-sm">XML</a>@endif
            @if ($n->status === 'processando')
                <form method="post" action="{{ route('notas.atualizar', $n) }}">@csrf<button class="btn btn-sm">Atualizar situação</button></form>
            @endif
            @if ($n->status === 'autorizada')
                <details class="w-full">
                    <summary class="cursor-pointer text-sm font-semibold text-erro">Cancelar nota</summary>
                    <form method="post" action="{{ route('notas.cancelar', $n) }}" class="mt-2 flex flex-wrap gap-2" data-confirmar="Cancelar esta nota fiscal?">
                        @csrf
                        <input name="justificativa" class="input max-w-md flex-1 py-1.5 text-sm" minlength="15" maxlength="255" required placeholder="Motivo (mínimo 15 letras)" aria-label="Motivo do cancelamento">
                        <button class="btn-perigo btn-sm">Cancelar nota</button>
                    </form>
                </details>
            @endif
        </div>
    </li>
@endforeach
