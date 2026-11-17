@php
    use App\Services\Cnh\AvaliacaoCnh;
    $rotulos = [
        'oficial' => 'Oficial (produção)', 'oficial_homologacao' => 'Oficial em homologação', 'documental' => 'Documental', 'nao_verificada' => 'Não verificada',
        'vigente' => 'Vigente', 'vencida' => 'Vencida', 'nao_informada' => 'Não informada',
        'compativel' => 'Compatível', 'incompativel' => 'Incompatível', 'informada_sem_comparacao' => 'Informada (sem comparação)',
        'regular' => 'Regular', 'irregular' => 'Com restrição', 'informada_sem_classificacao' => 'Informada (sem classificação)',
        'com_impedimentos' => 'Com impedimentos', 'sem_impedimentos' => 'Sem impedimentos',
        'conferido' => 'Sem divergências', 'divergente' => 'Divergente', 'inconclusivo' => 'Inconclusivo',
        'producao' => 'Libera retirada e locação', 'homologacao' => 'Não libera (homologação)', 'nao_liberado' => 'Não libera',
    ];
    $liberacao = $resultado['liberacao'] ?? 'nao_liberado';
@endphp
<x-app titulo="Resultado da consulta de CNH">
    <h1>Resultado da consulta de CNH</h1>
    <p class="mb-3 text-suave">Consulta processada em ambiente de {{ ($resultado['ambiente'] ?? 'homologacao') === 'producao' ? 'produção' : 'homologação' }}.</p>
    <p class="mb-5 font-semibold {{ $liberacao === 'producao' ? '' : 'text-suave' }}">{{ $rotulos[$liberacao] ?? $liberacao }}</p>
    <dl class="card card-corpo grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <dt class="text-sm text-suave">Garantia da fonte</dt>
            <dd class="font-semibold">{{ $resultado['garantia'] ?? 'Não informada.' }}</dd>
        </div>
        @foreach (($resultado['dimensoes'] ?? []) as $chave => $dimensao)
            <div>
                <dt class="text-sm text-suave">{{ AvaliacaoCnh::DIMENSOES[$chave] ?? $chave }}</dt>
                <dd class="font-semibold">{{ $rotulos[$dimensao['estado'] ?? ''] ?? ($dimensao['estado'] ?? '—') }}</dd>
                @if (($dimensao['valor'] ?? null) !== null && ($dimensao['valor'] ?? '') !== '')
                    <dd class="text-sm text-suave">Valor informado: {{ $dimensao['valor'] }}</dd>
                @endif
            </div>
        @endforeach
        <div>
            <dt class="text-sm text-suave">Resultado da conferência</dt>
            <dd class="font-semibold">{{ $rotulos[$resultado['resultado'] ?? ''] ?? ($resultado['resultado'] ?? '—') }}</dd>
        </div>
    </dl>
    @if (! empty($resultado['divergencias']))
        <div class="card card-corpo mt-4">
            <h2 class="mb-2">Divergências</h2>
            <ul class="list-disc pl-5 text-sm">
                @foreach ($resultado['divergencias'] as $divergencia)<li>{{ $divergencia }}</li>@endforeach
            </ul>
        </div>
    @endif
    <p class="mt-3 text-sm text-suave">A ausência de um campo significa que ele não foi informado pela fonte consultada e não é tratada como aprovação.</p>
    <a class="btn mt-4" href="{{ route('cnh.index') }}">Voltar a Condutores e CNH</a>
</x-app>
