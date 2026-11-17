<section class="card card-corpo scroll-mt-24 space-y-3" id="inspecao">
    <h2>Inspeção visual</h2>
    <p class="text-sm text-suave">Os registros aparecem no link do orçamento. Envie a imagem em <a class="underline" href="#fotos">Fotos</a> e selecione-a aqui para associá-la a um componente.</p>
    @include('os._inspecoes', ['publico' => false])
    @can('os.executar')
        @if ($os->status->aceitaItens())
            <form method="post" action="{{ route('os.inspecao.store', $os) }}" class="grid gap-3 sm:grid-cols-2">
                @csrf
                <x-campo nome="componente" rotulo="Componente (ex.: pneu dianteiro)" maxlength="100" required />
                <label class="campo"><span>Situação</span><select class="input" name="gravidade" required>
                    @foreach (\App\Models\OrdemServicoInspecao::GRAVIDADES as $valor => $rotulo)<option value="{{ $valor }}">{{ $rotulo }}</option>@endforeach
                </select></label>
                <label class="campo sm:col-span-2"><span>Explicação para o cliente</span><textarea class="input" name="observacao" maxlength="1500" rows="2">{{ old('observacao') }}</textarea></label>
                <label class="campo"><span>Foto da OS</span><select class="input" name="foto_id"><option value="">Sem foto</option>
                    @foreach ($os->fotos as $foto)<option value="{{ $foto->id }}">{{ $foto->legenda ?: 'Foto #'.$foto->id }}</option>@endforeach
                </select></label>
                <label class="campo"><span>Item do orçamento relacionado</span><select class="input" name="item_id"><option value="">Sem item</option>
                    @foreach ($os->itens as $item)<option value="{{ $item->id }}">{{ $item->descricao }}</option>@endforeach
                </select></label>
                <x-campo nome="revisar_em" rotulo="Data sugerida de revisão" tipo="date" />
                <x-campo nome="revisar_km" rotulo="Quilometragem sugerida" tipo="number" min="0" max="9999999" />
                <div><button class="btn-primario">Registrar inspeção</button></div>
            </form>
        @endif
    @endcan
</section>
