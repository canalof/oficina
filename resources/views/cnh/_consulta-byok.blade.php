@php
    use App\Models\ConferenciaCnh;
    use App\Services\Cnh\AvaliacaoCnh;
    use App\Services\Cnh\CatalogoCnh;
    $rotulos = [
        'oficial' => 'Oficial (produção)', 'oficial_homologacao' => 'Oficial em homologação', 'documental' => 'Documental', 'nao_verificada' => 'Não verificada',
        'vigente' => 'Vigente', 'vencida' => 'Vencida', 'nao_informada' => 'Não informada',
        'compativel' => 'Compatível', 'incompativel' => 'Incompatível', 'informada_sem_comparacao' => 'Informada (sem comparação)',
        'regular' => 'Regular', 'irregular' => 'Com restrição', 'informada_sem_classificacao' => 'Informada (sem classificação)',
        'com_impedimentos' => 'Com impedimentos', 'sem_impedimentos' => 'Sem impedimentos',
    ];
@endphp
<section class="card card-corpo mb-5 space-y-3">
    <h2>Consultar CNH com credenciais da oficina</h2>
    <p class="text-sm text-suave">Cada oficina usa o próprio contrato. O resultado de homologação não libera retirada, carro reserva ou locação.</p>
    @forelse ($provedoresByok as $integracao)
        <details>
            <summary class="cursor-pointer font-semibold">{{ CatalogoCnh::FORNECEDORES[$integracao->provedor][0] ?? $integracao->provedor }}</summary>
            <form method="post" enctype="multipart/form-data" action="{{ route('mobilidade.condutor.consultar', $condutor) }}" class="mt-3 space-y-3">
                @csrf
                <input type="hidden" name="provedor" value="{{ $integracao->provedor }}">
                @if ($os)<input type="hidden" name="os" value="{{ $os->id }}">@endif
                <label class="campo"><span>Finalidade</span><select name="finalidade" class="input" required>
                    @foreach ((array) $integracao->finalidades as $finalidade)
                        <option value="{{ $finalidade }}" @selected($os && $finalidade === 'retirada')>{{ ConferenciaCnh::FINALIDADES[$finalidade] ?? $finalidade }}</option>
                    @endforeach
                </select></label>
                @if ($integracao->provedor === 'senatran')
                    <p class="text-sm text-suave">
                        Consulta por CPF e registro cadastrados no condutor.
                        @if (! filled($condutor->cpf) || ! preg_match('/^\d{11}$/D', (string) $condutor->registro_cnh))
                            <strong>Cadastre o CPF e o registro da CNH antes de consultar.</strong>
                        @endif
                    </p>
                @else
                    <label class="campo"><span>Arquivo binário do QR de teste (até 16 KB)</span><input type="file" class="input" name="qr" accept=".bin,application/octet-stream" required></label>
                @endif
                <label class="block"><input type="checkbox" name="confirmacao" value="1" required> Confirmo a finalidade, a autorização e a ciência de que o resultado pode ser apenas de homologação.</label>
                <button class="btn-primario">Consultar</button>
            </form>
        </details>
    @empty
        <p>Nenhum provedor ativo para esta oficina.</p>
    @endforelse
    @can('empresa.configurar')<a class="btn btn-sm" href="{{ route('cnh.integracoes.index') }}">Configurar integrações</a>@endcan
    @foreach ($consultasByok as $consulta)
        @php
            $evidencias = (array) ($consulta->evidencias ?? []);
            $dimensoes = (array) ($evidencias['dimensoes'] ?? []);
            $liberacao = $evidencias['liberacao'] ?? null;
        @endphp
        <article class="rounded border border-linha p-3 text-sm">
            <h3>{{ CatalogoCnh::FORNECEDORES[$consulta->provedor][0] ?? $consulta->provedor }} · {{ $consulta->created_at->format('d/m/Y H:i') }}</h3>
            <p>Finalidade {{ ConferenciaCnh::FINALIDADES[$consulta->finalidade] ?? $consulta->finalidade }} · situação do registro: {{ $consulta->status }}</p>
            @if ($consulta->ordem_servico_id)<p>Vinculada à OS #{{ $consulta->ordem_servico_id }}</p>@endif
            @if ($liberacao)
                <p class="font-semibold">{{ AvaliacaoCnh::LIBERACOES[$liberacao] ?? $liberacao }}</p>
                <p class="text-suave">{{ $evidencias['garantia'] ?? '' }}</p>
                <ul class="list-disc pl-5">
                    @foreach (AvaliacaoCnh::DIMENSOES as $chave => $rotulo)
                        <li>{{ $rotulo }}: {{ $rotulos[$dimensoes[$chave]['estado'] ?? ''] ?? 'não informada' }}@if (($dimensoes[$chave]['valor'] ?? null) !== null && ($dimensoes[$chave]['valor'] ?? '') !== '') — {{ $dimensoes[$chave]['valor'] }}@endif</li>
                    @endforeach
                </ul>
                @if (! empty($evidencias['divergencias']))
                    <p class="font-semibold">Divergências</p>
                    <ul class="list-disc pl-5">@foreach ($evidencias['divergencias'] as $d)<li>{{ $d }}</li>@endforeach</ul>
                @endif
            @elseif (! empty($evidencias['motivo']))
                <p>Consulta não concluída ({{ $evidencias['motivo'] }}).</p>
            @else
                <p>Categoria de teste: {{ $evidencias['categoria'] ?? 'Não informada' }}</p>
                <p>Validade de teste: {{ $evidencias['validade'] ?? 'Não informada' }}</p>
                <p>Situação de teste: {{ $evidencias['situacao'] ?? 'Não informada' }}</p>
                <p>Impedimentos no retorno de teste: {{ $evidencias['impedimentos'] ?? 'Não informados' }}</p>
                @if ($evidencias['documento_teste'] ?? null)<p>Documento de teste identificado: {{ $evidencias['documento_teste'] }}</p>@endif
                <p class="text-suave">Registro anterior à separação das dimensões: não libera retirada nem locação.</p>
            @endif
        </article>
    @endforeach
    {{ $consultasByok->links() }}
</section>
