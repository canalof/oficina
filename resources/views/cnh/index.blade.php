@php
    use App\Models\ConferenciaCnh;
    use App\Services\Cnh\AvaliacaoCnh;
@endphp
<x-app titulo="Condutores e CNH">
    <h1>Condutores e CNH</h1>
    <p class="mb-5 text-suave">Confira o condutor na retirada de um veículo ou antes da locação.</p>
    @can('empresa.configurar')<a class="btn mb-4" href="{{ route('cnh.integracoes.index') }}">Integrações por oficina</a>@endcan
    <div class="mb-5 grid gap-4 lg:grid-cols-2">
        <section class="card card-corpo space-y-3">
            <h2>Conferência em canal oficial</h2>
            <p>Peça ao condutor que apresente a CNH. Confira a identidade, a data de validade e a categoria compatível com o veículo.</p>
            <p>A leitura de uma foto ou de um QR Code serve para auxiliar na conferência dos dados. A autenticidade e a situação atual dependem das integrações oficiais autorizadas.</p>
            @can('mobilidade.ver')<a class="btn btn-sm" href="{{ route('mobilidade.index') }}#condutores">Selecionar ou cadastrar condutor</a>@endcan
            <p class="text-sm text-suave">O registro manual guarda o resultado informado pela equipe. Para a retirada por terceiro, confira também a autorização do responsável pelo veículo.</p>
        </section>
        <section class="card card-corpo space-y-2">
            <h2>O que cada consulta comprova</h2>
            <p class="text-sm text-suave">Cada resultado é registrado em cinco dimensões separadas. Um campo ausente não é tratado como aprovado.</p>
            <dl class="text-sm">
                @foreach (AvaliacaoCnh::DIMENSOES as $chave => $rotulo)
                    <div class="border-t border-linha py-1">
                        <dt class="font-semibold">{{ $rotulo }}</dt>
                        <dd class="text-suave">
                            @switch($chave)
                                @case('autenticidade') Comprovada apenas por consulta oficial em produção; QR Code comprova o documento, não a situação no RENACH. @break
                                @case('validade') Comparada com a data de hoje. @break
                                @case('categoria') Comparada com a categoria exigida pela operação. @break
                                @case('situacao') Situação informada pelo órgão; ausente em leitura documental. @break
                                @default Impedimentos informados pelo órgão. @break
                            @endswitch
                        </dd>
                    </div>
                @endforeach
            </dl>
        </section>
    </div>
    <div class="mb-5 grid gap-4 lg:grid-cols-2">
        <form method="post" action="{{ route('cnh.store') }}" class="card card-corpo space-y-3">
            @csrf
            <h2>Registrar conferência</h2>
            @if ($os)
                <input type="hidden" name="ordem_servico_id" value="{{ $os->id }}">
                <p class="text-sm">Vinculado à <a class="font-semibold underline" href="{{ route('os.show', $os) }}">OS {{ $os->numeroFormatado() }}</a>.</p>
            @endif
            @if ($errors->any())<p class="erro-msg" role="alert">{{ $errors->first() }}</p>@endif
            @if ($condutores->isEmpty())
                <p class="text-sm">Nenhum condutor cadastrado. Cadastre o condutor antes de registrar a conferência declaratória.</p>
                @can('mobilidade.ver')<a class="btn btn-sm" href="{{ route('mobilidade.index') }}#condutores">Cadastrar condutor</a>@endcan
            @else
                <label class="campo"><span>Condutor cadastrado</span>
                    <select name="condutor_id" class="input" required>
                        <option value="">Selecione</option>
                        @foreach ($condutores as $condutor)
                            <option value="{{ $condutor->id }}" @selected((string) old('condutor_id') === (string) $condutor->id)>{{ $condutor->nome }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <p class="text-sm text-suave">A conferência manual é declaratória: fica vinculada ao condutor, mas não comprova autenticidade e não libera retirada nem locação.</p>
            @foreach ([
                'finalidade' => ['Finalidade', ConferenciaCnh::FINALIDADES],
                'metodo' => ['Como foi conferido', ConferenciaCnh::METODOS],
                'resultado' => ['Resultado da conferência', ConferenciaCnh::RESULTADOS],
            ] as $campo => [$rotulo, $opcoes])
                <label class="campo"><span>{{ $rotulo }}</span>
                    <select name="{{ $campo }}" class="input" required>
                        <option value="">Selecione</option>
                        @foreach ($opcoes as $valor => $descricao)
                            <option value="{{ $valor }}" @selected(old($campo, $os && $campo === 'finalidade' ? 'retirada' : '') === $valor)>{{ $descricao }}</option>
                        @endforeach
                    </select>
                </label>
            @endforeach
            <button class="btn-primario">Salvar conferência</button>
        </form>

        <section class="card card-corpo space-y-3">
            <h2>Consulta oficial no TORQUE</h2>
            <p class="text-sm">
                Ambiente desta instalação:
                <strong>{{ $ambienteConsulta['ambiente'] === 'producao' ? 'produção' : 'homologação' }}</strong>.
                @if (! $ambienteConsulta['configurado'])
                    <span class="text-suave">Integração ainda não configurada.</span>
                @elseif ($ambienteConsulta['ambiente'] !== 'producao')
                    <span class="text-suave">O resultado de homologação não libera retirada nem locação.</span>
                @endif
            </p>
            @if ($errors->has('cpf_condutor') || $errors->has('registro_cnh'))<p class="erro-msg" role="alert">{{ $errors->first() }}</p>@endif
            @if ($modoConsulta !== 'indisponivel')
                <form method="post" action="{{ route('cnh.consultar') }}" class="space-y-3">
                    @csrf
                    @if ($modoConsulta === 'byok')
                        <p class="text-sm text-suave">A consulta usa as credenciais cadastradas pela oficina em Integrações de CNH.</p>
                    @endif
                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="campo"><span>CPF do condutor</span><input class="input" name="cpf_condutor" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" autocomplete="off" required></label>
                        <label class="campo"><span>Registro da CNH</span><input class="input" name="registro_cnh" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" autocomplete="off" required></label>
                        <label class="campo"><span>Finalidade</span>
                            <select class="input" name="finalidade" required>
                                <option value="">Selecione</option>
                                @foreach ($finalidadesConsulta as $finalidade)<option value="{{ $finalidade }}">{{ ConferenciaCnh::FINALIDADES[$finalidade] }}</option>@endforeach
                            </select>
                        </label>
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="confirmacao" value="1" required> Confirmei a finalidade e a autorização aplicável para esta consulta.</label>
                    <button class="btn-primario">Consultar</button>
                </form>
            @else
                <p class="text-suave">Aguardando credenciamento da oficina e configuração do operador. A conferência manual continua disponível ao lado.</p>
            @endif
        </section>
    </div>

    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2>Histórico de conferências</h2>
        @if ($os)<a class="btn btn-sm" href="{{ route('cnh.index') }}">Ver todas</a>@endif
    </div>
    <div class="card overflow-x-auto">
        <table class="tabela">
            <thead><tr><th>Data</th><th>Condutor</th><th>Finalidade</th><th>Método</th><th>Resultado</th><th>Origem</th><th>Registrado por</th></tr></thead>
            <tbody>
                @forelse ($conferencias as $c)
                    <tr>
                        <td class="whitespace-nowrap">{{ $c->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $c->condutor }}</td>
                        <td>{{ ConferenciaCnh::FINALIDADES[$c->finalidade] ?? $c->finalidade }}
                            @if ($c->ordemServico)<a class="underline" href="{{ route('os.show', $c->ordemServico) }}">OS {{ $c->ordemServico->numeroFormatado() }}</a>@endif
                        </td>
                        <td>{{ ConferenciaCnh::METODOS[$c->metodo] ?? $c->metodo }}</td>
                        <td>{{ ConferenciaCnh::RESULTADOS[$c->resultado] ?? $c->resultado }}</td>
                        <td>@if (data_get($c->evidencias, 'origem') === 'declarado')<span class="text-suave">Declarada (não libera)</span>@else<span>Consulta registrada</span>@endif</td>
                        <td>{{ $c->usuario?->name ?? 'Usuário removido' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-suave">Nenhuma conferência registrada.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $conferencias->links() }}
    </div>
</x-app>
