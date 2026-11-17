@php use App\Models\OrdemServicoLaudo; @endphp
<div class="overflow-x-auto">
    <table class="tabela">
        <thead><tr><th>Roda</th>@foreach (OrdemServicoLaudo::CAMPOS as $rotulo)<th>{{ $rotulo }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach (OrdemServicoLaudo::RODAS as $roda => $nome)
                @if (isset($laudo->medicoes[$roda]))
                    <tr><th scope="row">{{ $nome }}</th>
                        @foreach (OrdemServicoLaudo::CAMPOS as $campo => $rotulo)
                            @php $valor = $laudo->medicoes[$roda][$campo] ?? null; @endphp
                            <td>{{ $valor === null ? '—' : number_format($valor, 2, ',', '.') }}</td>
                        @endforeach
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</div>
@if ($laudo->recomendacoes)
    <p class="mt-2 whitespace-pre-line">{{ $laudo->recomendacoes }}</p>
@endif
