@php use App\Support\Dinheiro; use App\Services\Financeiro\Financeiro; @endphp
<table class="tabela">
    <thead><tr><th>Hora</th><th>Descrição</th><th class="hidden sm:table-cell">Forma</th><th class="text-right">Valor</th></tr></thead>
    <tbody>
    @forelse ($movimentos as $m)
        <tr>
            <td class="whitespace-nowrap text-sm">{{ $m->created_at->format('H:i') }}<div class="text-xs text-suave">{{ $m->user?->name }}</div></td>
            <td class="text-sm">{{ $m->descricao }}<div class="text-xs text-suave">{{ ['recebimento' => 'Recebimento', 'pagamento' => 'Pagamento', 'suprimento' => 'Reforço de troco', 'sangria' => 'Retirada', 'estorno' => 'Estorno'][$m->tipo] }}</div></td>
            <td class="hidden text-sm sm:table-cell">{{ Financeiro::FORMAS[$m->forma] ?? $m->forma }}</td>
            <td @class(['whitespace-nowrap text-right font-semibold', 'text-ok' => $m->entrada(), 'text-erro' => ! $m->entrada()])>{{ $m->entrada() ? '+' : '−' }} {{ Dinheiro::formatar($m->valor_centavos) }}</td>
        </tr>
    @empty
        <tr><td colspan="4" class="py-8 text-center text-suave">Nenhuma movimentação ainda.</td></tr>
    @endforelse
    </tbody>
</table>
