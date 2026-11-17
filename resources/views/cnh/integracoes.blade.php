@php
    use App\Models\ConferenciaCnh;
    use App\Services\Cnh\AvaliacaoCnh;
    use App\Services\Cnh\CatalogoCnh;

    $origensRetencao = [
        'empresa' => 'definida pela oficina',
        'instalacao' => 'padrão da instalação',
        'tecnico' => 'limite técnico de segurança (prazo ainda não aprovado)',
    ];
@endphp
<x-app titulo="Integrações de CNH">
    <h1>Configurações · Integrações de CNH e Condutores</h1>
    <p class="my-4 text-suave">Cada oficina usa seu próprio contrato e paga diretamente ao fornecedor. Esta instalação opera em ambiente de <strong>{{ config('cnh.ambiente') === 'producao' ? 'produção' : 'homologação' }}</strong>.</p>
    @if (config('cnh.ambiente') !== 'producao')
        <p class="card card-corpo mb-4 text-sm">Enquanto o ambiente for de homologação, nenhuma consulta libera retirada ou locação. Para valer em produção defina, depois do credenciamento, <code>CNH_AMBIENTE=producao</code> e o endereço oficial do fornecedor.</p>
    @endif
    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($catalogo as $codigo => [$nome, $descricao, $link])
            @php
                $integracao = $integracoes->get($codigo);
                $disponivel = in_array($codigo, $implementados, true);
                $ambiente = $ambientes[$codigo] ?? ['ambiente' => config('cnh.ambiente'), 'configurado' => false, 'endereco' => null, 'mensagem' => null];
            @endphp
            <section class="card card-corpo space-y-3">
                <h2>{{ $nome }}</h2>
                <p>{{ $descricao }}</p>
                <p><a class="btn btn-sm" href="{{ $link }}" target="_blank" rel="noopener noreferrer">Contratação e requisitos</a></p>
                @if (! $disponivel)
                    <p class="font-semibold">Integração pendente</p>
                    <p class="text-sm text-suave">{{ $pendentes[$codigo] ?? 'Conector não implementado.' }}</p>
                @else
                    <p class="text-sm">Ambiente: <strong>{{ $ambiente['ambiente'] === 'producao' ? 'produção' : 'homologação' }}</strong>
                        @if ($ambiente['configurado'])
                            · <span class="text-suave">{{ $ambiente['endereco'] }}</span>
                        @else
                            · <span class="font-semibold">endpoint não configurado</span>
                        @endif
                    </p>
                    @if (! $ambiente['configurado'] && ($ambiente['mensagem'] ?? null))
                        <p class="text-sm text-suave">{{ $ambiente['mensagem'] }}</p>
                    @endif
                    @php
                        $dimensoesProvedor = array_map(
                            fn ($dimensao) => AvaliacaoCnh::DIMENSOES[$dimensao] ?? $dimensao,
                            CatalogoCnh::DADOS[$codigo] ?? []
                        );
                        $autenticidadeProvedor = $fontes[$codigo] ?? 'documental';
                    @endphp
                    <p class="text-sm text-suave">Dimensões devolvidas: {{ $dimensoesProvedor ? implode(', ', $dimensoesProvedor) : 'nenhuma (conector pendente)' }}</p>
                    <p class="text-sm">Autenticidade declarada pelo conector: <strong>{{ $autenticidadeProvedor === 'oficial' ? 'oficial (somente em produção libera)' : 'documental' }}</strong>. Nenhum resultado de homologação libera retirada ou locação.</p>
                    <p class="font-semibold">{{ $integracao?->ativa ? 'Ativa' : ($integracao ? 'Desativada' : 'Sem configuração') }}</p>
                    @if ($integracao?->testada_em)
                        <p class="text-sm">Último teste {{ $integracao->testada_em->format('d/m/Y H:i') }} · {{ $integracao->teste_ok ? 'Confirmado' : 'Não confirmado' }}</p>
                    @endif
                    <p class="text-sm">Tentativas neste mês {{ $consumo->get($codigo, 0) }}. Valores e faturas são consultados com o fornecedor.</p>
                    <details>
                        <summary class="cursor-pointer font-semibold">{{ $integracao ? 'Substituir credenciais' : 'Configurar credenciais' }}</summary>
                        <form method="post" autocomplete="off" action="{{ route('cnh.integracoes.salvar', $codigo) }}" class="mt-3 space-y-3">
                            @csrf @method('put')
                            @if ($codigo === 'senatran')
                                <label class="campo"><span>Certificado PEM</span><textarea class="input" name="credenciais[certificado_pem]" required rows="2" maxlength="10000" spellcheck="false"></textarea></label>
                                <label class="campo"><span>Chave privada PEM</span><textarea class="input" name="credenciais[chave_pem]" required rows="2" maxlength="8000" spellcheck="false"></textarea></label>
                                <label class="campo"><span>CPF do operador autorizado no contrato</span><input class="input" type="password" name="credenciais[cpf_operador]" maxlength="11" inputmode="numeric" required></label>
                            @else
                                <label class="campo"><span>Consumer Key</span><input class="input" type="password" name="credenciais[consumer_key]" maxlength="255" required></label>
                                <label class="campo"><span>Consumer Secret</span><input class="input" type="password" name="credenciais[consumer_secret]" maxlength="255" required></label>
                            @endif
                            @if ($integracao)
                                <label class="campo"><span>Sua senha atual (confirmação de identidade)</span><input class="input" type="password" name="senha_atual" autocomplete="current-password" required></label>
                                <p class="text-sm text-suave">Substituir credenciais exige confirmar sua senha. A ação é registrada na auditoria sem qualquer segredo.</p>
                            @endif
                            <p class="text-sm text-suave">As credenciais ficam cifradas no banco e são compartilhadas com os usuários autorizados da oficina; o autor do cadastro é registrado para auditoria.</p>
                            @foreach (ConferenciaCnh::FINALIDADES as $valor => $rotulo)
                                <label class="block"><input type="checkbox" name="finalidades[]" value="{{ $valor }}" @checked(in_array($valor, $integracao?->finalidades ?? [], true))> {{ $rotulo }}</label>
                            @endforeach
                            <label class="block"><input type="checkbox" name="confirmacao_contrato" value="1" required> Confirmo o contrato da oficina, a autorização para o uso pelo TORQUE e as finalidades selecionadas.</label>
                            <button class="btn-primario">Salvar credenciais</button>
                        </form>
                    </details>
                    @if ($integracao)
                        <form method="post" action="{{ route('cnh.integracoes.testar', $codigo) }}" class="space-y-2">
                            @csrf
                            @if ($codigo === 'senatran')
                                <label class="campo"><span>CPF da massa de testes autorizada</span><input class="input" name="cpf_teste" maxlength="11" inputmode="numeric" autocomplete="off" required></label>
                                <label class="campo"><span>Registro da CNH de teste</span><input class="input" name="registro_teste" maxlength="11" inputmode="numeric" autocomplete="off" required></label>
                                <label class="block"><input type="checkbox" name="confirmacao_teste" value="1" required> Estou usando dados de teste autorizados.</label>
                            @endif
                            <button class="btn">Testar conexão</button>
                        </form>
                        @if ($integracao->teste_ok && ! $integracao->ativa)
                            <form method="post" action="{{ route('cnh.integracoes.estado', $codigo) }}" class="space-y-2">
                                @csrf
                                <label class="block"><input type="checkbox" name="confirmacao" value="1" required> Confirmo a autorização para ativar o uso neste ambiente.</label>
                                <button class="btn-primario" name="acao" value="ativar">Ativar</button>
                            </form>
                        @endif
                        @if ($integracao->ativa)
                            <form method="post" action="{{ route('cnh.integracoes.estado', $codigo) }}">
                                @csrf
                                <button class="btn" name="acao" value="desativar">Desativar</button>
                            </form>
                        @endif
                        <form method="post" action="{{ route('cnh.integracoes.estado', $codigo) }}" class="space-y-2">
                            @csrf
                            <label class="campo"><span>Sua senha atual (confirmação de identidade)</span><input class="input" type="password" name="senha_atual" autocomplete="current-password" required></label>
                            <p class="text-sm text-suave">Remover as credenciais exige confirmar sua senha. A remoção é auditada e não expõe segredos.</p>
                            <button class="btn" name="acao" value="revogar">Remover credenciais do TORQUE</button>
                        </form>
                    @endif
                @endif
            </section>
        @endforeach
    </div>
    <section class="card card-corpo mt-4 space-y-3">
        <h2>Retenção de evidências de CNH</h2>
        <p class="text-sm">Política efetiva desta oficina: <strong>{{ $retencao['dias'] }} dias</strong> · {{ $origensRetencao[$retencao['origem']] ?? $retencao['origem'] }}.</p>
        <p class="text-sm">Expurgo automático: <strong>{{ $expurgoAtivo ? 'ativo' : 'desativado nesta etapa' }}</strong>. Nenhuma evidência é apagada por esta tela.</p>
        @if (! $retencao['definida'])
            <p class="text-sm text-suave">Ainda não há prazo aprovado para esta oficina; aplica-se o limite técnico de segurança. Defina um prazo entre {{ $retencaoLimites['minimo'] }} e {{ $retencaoLimites['maximo'] }} dias.</p>
        @endif
        @if ($errors->has('cnh_retencao_dias'))
            <p class="erro-msg" role="alert">{{ $errors->first('cnh_retencao_dias') }}</p>
        @endif
        <form method="post" action="{{ route('cnh.integracoes.retencao') }}" class="space-y-3">
            @csrf @method('put')
            <label class="campo"><span>Prazo de retenção em dias ({{ $retencaoLimites['minimo'] }} a {{ $retencaoLimites['maximo'] }})</span>
                <input class="input" type="number" name="cnh_retencao_dias" inputmode="numeric" min="{{ $retencaoLimites['minimo'] }}" max="{{ $retencaoLimites['maximo'] }}" value="{{ old('cnh_retencao_dias', $retencao['origem'] === 'empresa' ? $retencao['dias'] : '') }}">
            </label>
            <p class="text-sm text-suave">Deixe em branco para voltar ao padrão da instalação. Zero não significa retenção ilimitada. A alteração é auditada e não liga o expurgo.</p>
            <button class="btn">Salvar política de retenção</button>
        </form>
    </section>
    <section class="card card-corpo mt-4">
        <h2>Histórico de tentativas</h2>
        @forelse ($consultas as $consulta)
            @php
                $evidencias = (array) ($consulta->evidencias ?? []);
                $vinculo = $consulta->condutor_id ? 'Condutor #'.$consulta->condutor_id : 'Sem condutor vinculado';
                if ($consulta->titular_hash) {
                    $vinculo .= ' · titular '.substr($consulta->titular_hash, 0, 12).'…';
                }
            @endphp
            <p class="mt-2 text-sm">{{ $consulta->created_at->format('d/m/Y H:i') }} · {{ $catalogo[$consulta->provedor][0] ?? $consulta->provedor }} · {{ $consulta->finalidade }} · {{ $consulta->status }} · {{ $vinculo }}@if (! empty($evidencias['motivo'])) · motivo {{ $evidencias['motivo'] }}@endif</p>
        @empty
            <p>Nenhuma tentativa registrada.</p>
        @endforelse
        {{ $consultas->links() }}
    </section>
</x-app>
