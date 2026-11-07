<x-app :titulo="'Histórico · '.$titulo">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1>Histórico de alterações</h1>
            <p class="text-suave">{{ $titulo }}</p>
        </div>
        <a href="{{ $voltar }}" class="btn">Voltar</a>
    </div>
    @forelse ($entradas as $e)
        <section class="card mb-3">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-linha px-5 py-3">
                <span class="font-semibold">{{ ['criou' => 'Cadastrado', 'alterou' => 'Alterado', 'excluiu' => 'Excluído'][$e['acao']] ?? $e['acao'] }} por {{ $e['quem'] }}@if($e['motivo'])<span class="font-normal text-suave"> · {{ $e['motivo'] }}</span>@endif</span>
                <span class="text-sm text-suave">{{ $e['quando']->format('d/m/Y H:i') }}{{ $e['ip'] ? ' · IP '.$e['ip'] : '' }}</span>
            </div>
            @if ($e['campos'])
                <div class="overflow-x-auto">
                    <table class="tabela">
                        <tbody>
                        @foreach ($e['campos'] as $c)
                            <tr>
                                <td class="w-48 text-sm text-suave">{{ $c['rotulo'] }}</td>
                                @if ($e['acao'] === 'alterou')
                                    <td class="text-sm"><span class="text-suave line-through">{{ $c['antes'] }}</span> → <span class="font-medium">{{ $c['depois'] }}</span></td>
                                @else
                                    <td class="text-sm">{{ $e['acao'] === 'excluiu' ? $c['antes'] : $c['depois'] }}</td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @empty
        <div class="card px-5 py-12 text-center text-suave">Nenhuma alteração registrada.</div>
    @endforelse
</x-app>
