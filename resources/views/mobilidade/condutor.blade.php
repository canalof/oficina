@php use App\Models\ConferenciaCnh; @endphp
<x-app titulo="Consulta e Verificação de CNH">
    <h1>Consulta e Verificação de CNH</h1>
    <p class="mb-4 text-suave">{{ $condutor->nome }} @if($os) · OS {{ $os->numeroFormatado() }} @endif</p>
    <div class="mb-5 flex flex-wrap gap-2">
        <a class="btn" href="{{ route('mobilidade.index', ['os' => $os?->id]) }}">Voltar a Mobilidade</a>
        <a class="btn" href="{{ route('cnh.index', ['os' => $os?->id]) }}">Consulta oficial e histórico geral</a>
    </div>
    @if ($errors->any())<p class="erro-msg mb-4">{{ $errors->first() }}</p>@endif
    @include('cnh._consulta-byok')
    @can('mobilidade.editar')
        <div class="mb-5 grid gap-4 lg:grid-cols-2">
            <form method="post" action="{{ route('mobilidade.condutor.update', ['condutor' => $condutor, 'os' => $os?->id]) }}" class="card card-corpo space-y-3" data-cadastro-condutor>
                @csrf @method('put')
                <h2>Cadastro do condutor</h2>
                @include('mobilidade._condutor-campos')
                <button class="btn-primario">Salvar dados</button>
            </form>
            <section class="card card-corpo space-y-3" data-documento-cnh>
                <h2>Ler documento neste aparelho</h2>
                <p>Fotografe a CNH ou selecione uma imagem. O TORQUE tenta ler CPF, validade, categoria e detectar o QR Code. Confira os dados antes de salvar ou registrar a conferência.</p>
                <label class="campo"><span>Foto da CNH</span><input class="input" type="file" accept="image/*" capture="environment"></label>
                <p class="whitespace-pre-line text-sm text-suave" data-documento-resultado aria-live="polite">A imagem e o QR Code não são enviados ao servidor nesta leitura.</p>
            </section>
        </div>
        <form method="post" action="{{ route('mobilidade.condutor.verificar', $condutor) }}" class="card card-corpo mb-5 space-y-3">
            @csrf
            @if ($os)<input type="hidden" name="ordem_servico_id" value="{{ $os->id }}">@endif
            <h2>Registrar conferência assistida</h2>
            <p class="text-sm text-suave">Confira a identidade e os campos apresentados. Esta etapa registra o que a equipe conferiu no documento.</p>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach (['finalidade' => ['Finalidade', ConferenciaCnh::FINALIDADES], 'resultado' => ['Resultado', ConferenciaCnh::RESULTADOS]] as $campo => [$rotulo, $opcoes])
                    <label class="campo"><span>{{ $rotulo }}</span><select class="input" name="{{ $campo }}" required>
                        <option value="">Selecione</option>
                        @foreach ($opcoes as $valor => $texto)<option value="{{ $valor }}">{{ $texto }}</option>@endforeach
                    </select></label>
                @endforeach
            </div>
            <label class="flex gap-2"><input type="checkbox" name="dados_conferidos" value="1"> Dados do cadastro conferidos com o documento apresentado</label>
            <label class="flex gap-2"><input type="checkbox" name="categoria_compativel" value="1"> Categoria informada compatível com o veículo desta operação</label>
            <button class="btn-primario">Salvar conferência</button>
        </form>
    @endcan
    <div class="card card-corpo space-y-3">
        <h2>Histórico de verificações</h2>
        @forelse ($conferencias as $c)
            @php $e = $c->evidencias ?? []; @endphp
            <div class="rounded-md border border-linha p-3">
                <p class="font-semibold">{{ $c->created_at->format('d/m/Y H:i') }} · {{ ConferenciaCnh::RESULTADOS[$c->resultado] }}</p>
                <p class="text-sm text-suave">Origem: conferência assistida · Operador #{{ $c->user_id ?? '—' }} · {{ ConferenciaCnh::FINALIDADES[$c->finalidade] }}</p>
                <p class="text-sm">Dados: {{ ($e['dados_conferidos'] ?? false) ? 'conferidos' : 'não confirmados' }} · Validade aparente: {{ ['vencida' => 'vencida', 'na_data' => 'dentro da data informada'][$e['validade_aparente'] ?? ''] ?? 'não informada' }} · Categoria: {{ $e['categoria_informada'] ?? '—' }} ({{ ($e['categoria_compativel'] ?? false) ? 'compatibilidade conferida' : 'compatibilidade não confirmada' }})</p>
                <p class="text-sm">Autenticidade: não verificada · Situação atual do direito de dirigir: não verificada</p>
            </div>
        @empty
            <p class="text-suave">Nenhuma conferência deste condutor.</p>
        @endforelse
        {{ $conferencias->links() }}
    </div>
</x-app>
