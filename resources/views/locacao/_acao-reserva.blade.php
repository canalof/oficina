<form method="post" action="{{ route('locacao.reserva.acao', [$reserva, $acao]) }}" class="mt-3 space-y-3 rounded-md border border-linha p-3">
    @csrf
    <h3 class="font-semibold">{{ $acao === 'devolver' ? 'Registrar devolução' : 'Registrar saída do carro' }}</h3>
    <div class="grid gap-3 sm:grid-cols-2">
        <x-campo nome="km" rotulo="Quilometragem" tipo="number" min="0" required />
        <label class="campo"><span>Combustível</span><select class="input" name="combustivel" required>
            @foreach (['Vazio', '¼', '½', '¾', 'Cheio'] as $valor => $rotulo)<option value="{{ $valor }}">{{ $rotulo }}</option>@endforeach
        </select></label>
    </div>
    <label class="campo"><span>Vistoria e avarias</span><textarea class="input" name="vistoria" maxlength="4000" required rows="2"></textarea></label>
    <x-campo nome="aceite_nome" rotulo="Nome de quem deu o aceite presencial" required maxlength="120" />
    @if ($acao === 'devolver')
        <x-campo nome="taxas" rotulo="Taxas adicionais (R$)" mascara="dinheiro" inputmode="decimal" ajuda="Combustível, limpeza, km extra. Entra no título a receber junto com as diárias." />
        <label class="campo"><span>Ocorrências, multas e observações sobre caução</span><textarea class="input" name="ocorrencias" maxlength="4000" rows="2"></textarea></label>
    @endif
    <button class="btn-primario">{{ $acao === 'devolver' ? 'Registrar devolução' : 'Registrar saída' }}</button>
</form>
