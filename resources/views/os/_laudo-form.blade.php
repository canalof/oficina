@php
    use App\Models\OrdemServicoLaudo;
    use App\Support\StatusOs;
@endphp
@if ($os->laudo || ($podeExecutar && $st !== StatusOs::Cancelada))
    <section id="laudo-alinhamento" class="card card-corpo scroll-mt-24 space-y-3">
        <h2>Laudo de alinhamento e pneus</h2>
        @if ($os->laudo)
            <p class="text-sm text-suave">{{ $os->laudo->publicado ? 'Visível no portal do cliente' : 'Rascunho interno' }}</p>
            @include('os._laudo-medicoes', ['laudo' => $os->laudo])
        @endif
        @if ($podeExecutar && $st !== StatusOs::Cancelada)
            <details @if($errors->has('medicoes') || $errors->has('medicoes.*') || $errors->has('recomendacoes')) open @endif>
                <summary class="cursor-pointer font-semibold">{{ $os->laudo ? 'Editar laudo' : 'Registrar medições' }}</summary>
                <form method="post" action="{{ route('os.laudo.update', $os) }}" class="mt-3 space-y-3">
                    @csrf @method('put')
                    <p class="text-sm text-suave">Use graus decimais conforme o equipamento. Deixe em branco as medidas que não foram feitas.</p>
                    @foreach (OrdemServicoLaudo::RODAS as $roda => $nome)
                        <fieldset class="grid gap-2 rounded border border-linha p-3 sm:grid-cols-2">
                            <legend class="px-1 font-medium">{{ $nome }}</legend>
                            @foreach (OrdemServicoLaudo::CAMPOS as $campo => $rotulo)
                                <label class="campo"><span>{{ $rotulo }}</span>
                                    <input class="input" type="number" step="0.01" min="{{ $campo === 'sulco' ? 0 : -90 }}" max="{{ $campo === 'sulco' ? 30 : 90 }}" name="medicoes[{{ $roda }}][{{ $campo }}]" value="{{ old('medicoes.'.$roda.'.'.$campo, $os->laudo?->medicoes[$roda][$campo] ?? '') }}">
                                </label>
                                @error('medicoes.'.$roda.'.'.$campo)<p class="erro-msg">{{ $message }}</p>@enderror
                            @endforeach
                        </fieldset>
                    @endforeach
                    @error('medicoes')<p class="erro-msg">{{ $message }}</p>@enderror
                    <label class="campo"><span>Recomendações para o cliente</span><textarea class="input" name="recomendacoes" rows="3" maxlength="2000">{{ old('recomendacoes', $os->laudo?->recomendacoes) }}</textarea></label>
                    @error('recomendacoes')<p class="erro-msg">{{ $message }}</p>@enderror
                    <input type="hidden" name="publicado" value="0">
                    <label class="flex items-center gap-2"><input type="checkbox" name="publicado" value="1" @checked(old('publicado', $os->laudo?->publicado ?? false))> Publicar no portal do cliente</label>
                    <button class="btn-primario">Salvar laudo</button>
                </form>
            </details>
        @endif
    </section>
@endif
