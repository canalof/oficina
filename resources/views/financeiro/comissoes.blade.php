@php use App\Support\Dinheiro; @endphp
<x-app titulo="Comissões">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1>Comissões</h1>
            <p class="text-suave">Calculadas na entrega de cada carro, sobre os serviços. O percentual de cada pessoa fica em Usuários.</p>
        </div>
        <form class="flex items-end gap-2"><x-campo nome="mes" rotulo="Mês" tipo="month" :valor="$mes->format('Y-m')" /><button class="btn">Ver</button></form>
    </div>
    @forelse ($porPessoa as $userId => $lista)
        @php $pendente = $lista->whereNull('paga_em')->sum('valor_centavos'); @endphp
        <section class="card mb-4 overflow-x-auto">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-linha px-5 py-4">
                <div><h2>{{ $lista->first()->user->name }}</h2><p class="text-sm text-suave">{{ $lista->count() }} OS · total {{ Dinheiro::formatar($lista->sum('valor_centavos')) }}</p></div>
                @if ($pendente && auth()->user()->can('financeiro.editar'))
                    <form method="post" action="{{ route('comissoes.pagar') }}" data-confirmar="Marcar {{ Dinheiro::formatar($pendente) }} como pago?">
                        @csrf <input type="hidden" name="user_id" value="{{ $userId }}"><input type="hidden" name="mes" value="{{ $mes->format('Y-m') }}">
                        <button class="btn-primario btn-sm">Marcar {{ Dinheiro::formatar($pendente) }} como pago</button>
                    </form>
                @elseif (! $pendente)
                    <span class="rotulo border-[#b7dcc6] bg-[#eef8f2] text-ok">Pago</span>
                @endif
            </div>
            <table class="tabela">
                <thead><tr><th>OS</th><th class="text-right">Serviços</th><th class="text-right">%</th><th class="text-right">Comissão</th></tr></thead>
                <tbody>
                @foreach ($lista as $c)
                    <tr>
                        <td><a href="{{ route('os.show', $c->ordem_servico_id) }}" class="hover:underline">{{ $c->ordemServico->numeroFormatado() }} · {{ $c->ordemServico->veiculo->placaFormatada() }}</a></td>
                        <td class="text-right">{{ Dinheiro::formatar($c->base_centavos) }}</td>
                        <td class="text-right">{{ rtrim(rtrim((string) $c->percentual, '0'), '.') }}%</td>
                        <td class="text-right font-semibold">{{ Dinheiro::formatar($c->valor_centavos) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @empty
        <div class="card px-5 py-12 text-center text-suave">Nenhuma comissão em {{ $mes->translatedFormat('F/Y') }}.</div>
    @endforelse
</x-app>
