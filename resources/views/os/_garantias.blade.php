@if ($os->status === \App\Support\StatusOs::Entregue || $os->garantias->isNotEmpty())
    <section class="card card-corpo space-y-3" id="garantias">
        <h2>Garantias e devoluções</h2>
        <p class="text-sm text-suave">Controle interno de ocorrências da OS original.</p>
        @foreach ($os->garantias as $garantia)
            <article class="space-y-2 rounded border border-linha p-3">
                <h3>{{ $garantia->peca }} · {{ \App\Models\OrdemServicoGarantia::STATUS[$garantia->status] }}</h3>
                <p>{{ $garantia->fornecedor }} {{ $garantia->prazo ? '· Prazo '.$garantia->prazo->format('d/m/Y') : '' }}</p>
                <p>{{ $garantia->relato }}</p>
                @if ($garantia->foto_id)<a class="underline" href="{{ route('os.fotos.show', [$os, $garantia->foto_id]) }}">Foto da OS</a>@endif
                @if ($garantia->retorno_id)
                    <a class="btn btn-sm" href="{{ route('os.show', $garantia->retorno_id) }}">Abrir OS de retorno</a>
                @elseif ($os->status === \App\Support\StatusOs::Entregue)
                    @can('os.editar')
                        <form method="post" action="{{ route('os.garantias.retorno', [$os, $garantia]) }}">
                            @csrf <button class="btn btn-sm">Criar OS de retorno em garantia</button>
                        </form>
                    @endcan
                @endif
                @can('os.editar')
                    <form method="post" class="space-y-2" action="{{ route('os.garantias.update', [$os, $garantia]) }}">
                        @csrf @method('put')
                        <label class="campo"><span>Status</span><select class="input" name="status">
                            @foreach (\App\Models\OrdemServicoGarantia::STATUS as $valor => $rotulo)
                                <option value="{{ $valor }}" @selected($garantia->status === $valor)>{{ $rotulo }}</option>
                            @endforeach
                        </select></label>
                        <label class="campo"><span>Retorno do fornecedor ou resolução</span><textarea class="input" name="resolucao" maxlength="2000">{{ $garantia->resolucao }}</textarea></label>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <x-campo nome="custo_pecas_centavos" rotulo="Custo de peças em centavos" tipo="number" min="0" :valor="$garantia->custo_pecas_centavos" />
                            <x-campo nome="custo_mao_obra_centavos" rotulo="Custo de mão de obra em centavos" tipo="number" min="0" :valor="$garantia->custo_mao_obra_centavos" />
                        </div>
                        <button class="btn btn-sm">Atualizar ocorrência</button>
                    </form>
                @else
                    <p>{{ $garantia->resolucao }}</p>
                @endcan
            </article>
        @endforeach
        @can('os.editar')
            @if ($os->status === \App\Support\StatusOs::Entregue)
                <details>
                    <summary class="cursor-pointer font-semibold">Registrar ocorrência</summary>
                    <form class="mt-3 space-y-3" method="post" action="{{ route('os.garantias.store', $os) }}">
                        @csrf
                        <x-campo nome="peca" rotulo="Peça ou serviço" maxlength="150" required />
                        <x-campo nome="fornecedor" rotulo="Fornecedor" maxlength="150" />
                        <x-campo nome="prazo" rotulo="Prazo de retorno" tipo="date" />
                        <label class="campo"><span>Foto da OS</span><select class="input" name="foto_id"><option value="">Sem foto</option>
                            @foreach ($os->fotos as $foto)<option value="{{ $foto->id }}">{{ $foto->legenda ?: 'Foto #'.$foto->id }}</option>@endforeach
                        </select></label>
                        <label class="campo"><span>Relato</span><textarea class="input" name="relato" maxlength="2000" required></textarea></label>
                        <button class="btn-primario">Salvar ocorrência</button>
                    </form>
                </details>
            @endif
        @endcan
    </section>
@endif
