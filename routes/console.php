<?php

use App\Models\Categoria;
use App\Models\CategoriaServico;
use App\Models\Empresa;
use App\Models\Loja;
use App\Models\User;
use App\Support\Documento;
use App\Support\Perfil;
use App\Support\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

/*
 * Cria um cliente novo do sistema (empresa + loja matriz + usuário dono).
 * Uso: php artisan torque:empresa "Autocenter Silva" dono@autocentersilva.com.br --cnpj=11222333000181 --nome="José Silva"
 */
Artisan::command('torque:empresa {nome_fantasia} {email} {--cnpj=} {--nome=Responsável} {--senha=} {--plano=profissional}', function () {
    $email = strtolower($this->argument('email'));
    if (User::where('email', $email)->exists()) {
        $this->error("Já existe usuário com o e-mail {$email}.");

        return 1;
    }
    $cnpj = Documento::normalizar($this->option('cnpj')) ?: null;
    if ($cnpj && ! Documento::cnpjValido($cnpj)) {
        $this->error('CNPJ inválido.');

        return 1;
    }
    $senha = $this->option('senha') ?: Str::password(12, symbols: false);

    DB::transaction(function () use ($cnpj, $email, $senha) {
        $empresa = Empresa::create([
            'nome_fantasia' => $this->argument('nome_fantasia'), 'cnpj' => $cnpj,
            'plano_id' => \App\Models\Plano::where('slug', $this->option('plano'))->value('id'),
            'assinatura_status' => 'trial', 'trial_ate' => today()->addDays(Empresa::DIAS_TRIAL),
        ]);
        Categoria::criarPadroes($empresa->id);
        CategoriaServico::criarPadroes($empresa->id);
        Tenant::com($empresa->id, function () use ($empresa, $email, $senha) {
            $loja = Loja::create(['nome' => 'Matriz', 'cnpj' => $empresa->cnpj]);
            User::create([
                'empresa_id' => $empresa->id, 'loja_id' => $loja->id, 'name' => $this->option('nome'),
                'email' => $email, 'perfil' => Perfil::Dono, 'ativo' => true, 'password' => $senha,
            ]);
        });
        $this->info("Empresa criada (id {$empresa->id}).");
    });

    $this->line("Login: {$email}");
    $this->line("Senha: {$senha}");
    $this->comment('Peça para trocar a senha no primeiro acesso (menu Trocar senha).');

    return 0;
})->purpose('Cria uma empresa cliente com o usuário dono');

/*
 * Atualiza notas fiscais que ainda estão processando na prefeitura/SEFAZ.
 */
Artisan::command('torque:notas', function (\App\Services\Fiscal\NotasFiscais $servico) {
    $n = 0;
    foreach (Empresa::where('ativo', true)->pluck('id') as $empresaId) {
        Tenant::com($empresaId, function () use ($servico, &$n) {
            foreach (\App\Models\NotaFiscal::where('status', 'processando')->where('created_at', '>', now()->subDays(3))->get() as $nota) {
                $servico->atualizar($nota);
                $n++;
            }
        });
    }
    $this->info("{$n} nota(s) consultada(s).");
})->purpose('Consulta as notas fiscais em processamento');

/*
 * Envia lembretes de revisão e confirmações de agenda pelo WhatsApp automático (Evolution API).
 * Só age nas empresas com instância configurada.
 */
Artisan::command('torque:lembretes', function (\App\Services\WhatsApp\WhatsApp $whatsapp) {
    $enviados = 0;
    foreach (Empresa::where('ativo', true)->whereNotNull('whatsapp_instancia')->get() as $empresa) {
        if (! $whatsapp->disponivel($empresa)) {
            continue;
        }
        Tenant::com($empresa->id, function () use ($empresa, $whatsapp, &$enviados) {
            $lembretes = \App\Models\Lembrete::with(['cliente', 'veiculo'])->where('status', 'pendente')
                ->whereBetween('data_prevista', [today(), today()->addDays(7)])->get();
            foreach ($lembretes as $l) {
                if ($l->cliente?->aceita_whatsapp && ! $l->cliente->trashed() && $l->veiculo && ! $l->veiculo->trashed()
                    && (! $l->inspecao_id || $l->veiculo->cliente_id === $l->cliente->id)
                    && $whatsapp->enviar($empresa, $l->cliente->whatsapp ?: $l->cliente->telefone, $l->mensagem($empresa->nome_fantasia), 'lembrete', $l->id)) {
                    $l->update(['status' => 'enviado', 'enviado_em' => now()]);
                    $enviados++;
                }
            }
            $amanha = \App\Models\Agendamento::with('cliente')->whereBetween('inicio', [today()->addDay(), today()->addDay()->endOfDay()])
                ->whereIn('status', ['agendado', 'confirmado'])->whereNull('lembrete_enviado_em')->get();
            foreach ($amanha as $a) {
                if ($a->cliente && ! $a->cliente->aceita_whatsapp) {
                    continue; // cliente pediu para não receber
                }
                $txt = 'Olá, '.explode(' ', trim($a->nome))[0].'! Lembrando do seu horário amanhã às '.$a->inicio->format('H:i')
                    .' na '.$empresa->nome_fantasia.' ('.$a->servico.'). Se precisar remarcar, é só responder.';
                if ($whatsapp->enviar($empresa, $a->telefone, $txt, 'agenda', $a->id)) {
                    $a->update(['lembrete_enviado_em' => now()]);
                    $enviados++;
                }
            }
            // Nova tentativa das que falharam nas últimas 24 h (até 3 tentativas)
            foreach (\App\Models\MensagemWhatsapp::where('status', 'falhou')->where('tentativas', '<', 3)->where('created_at', '>', now()->subDay())->get() as $m) {
                $whatsapp->tentar($empresa, $m) && $enviados++;
            }
        });
    }
    $this->info("{$enviados} mensagem(ns) enviada(s).");
})->purpose('Envia lembretes de revisão e de agenda pelo WhatsApp');

/*
 * Teste vencido e atraso de pagamento além da tolerância: suspende o acesso.
 */
Artisan::command('torque:assinaturas', function (\App\Services\Cobranca\Assinaturas $a) {
    $this->info($a->verificarVencimentos().' empresa(s) suspensa(s).');
})->purpose('Suspende testes vencidos e assinaturas em atraso');

/*
 * Cria (ou promove) o administrador da plataforma. Ele precisa ativar a verificação em duas etapas no primeiro acesso.
 */
Artisan::command('torque:admin {email} {--nome=Administrador} {--senha=}', function () {
    $email = strtolower($this->argument('email'));
    $senha = $this->option('senha') ?: Str::password(14, symbols: false);
    $u = User::where('email', $email)->first();
    if (! $u) {
        $empresa = Empresa::firstOrCreate(['nome_fantasia' => 'Torque (plataforma)'], ['assinatura_status' => 'ativa']);
        $u = Tenant::com($empresa->id, fn () => User::create([
            'empresa_id' => $empresa->id, 'name' => $this->option('nome'), 'email' => $email, 'perfil' => Perfil::Dono, 'ativo' => true, 'password' => $senha,
        ]));
        $this->line("Senha: {$senha}");
    }
    $u->forceFill(['admin_plataforma' => true])->save();
    $this->info("{$email} é administrador da plataforma. No primeiro acesso, ele ativa a verificação em duas etapas.");
})->purpose('Cria ou promove o administrador da plataforma');

/*
 * Backup do banco (MySQL via mysqldump ou cópia do SQLite) em storage/app/backups, guardando os 14 mais recentes.
 */
Artisan::command('torque:backup {--manter=14}', function () {
    $pasta = storage_path('app/backups');
    @mkdir($pasta, 0750, true);
    $nome = $pasta.'/torque-'.now()->format('Y-m-d-His');
    $con = config('database.default');
    $cfg = config("database.connections.{$con}");
    if ($cfg['driver'] === 'sqlite') {
        copy($cfg['database'], $nome.'.sqlite');
        $arquivo = $nome.'.sqlite';
    } else {
        $arquivo = $nome.'.sql.gz';
        $cmd = sprintf('MYSQL_PWD=%s mysqldump --single-transaction --routines -h %s -P %s -u %s %s | gzip > %s',
            escapeshellarg((string) $cfg['password']), escapeshellarg((string) $cfg['host']), escapeshellarg((string) $cfg['port']),
            escapeshellarg((string) $cfg['username']), escapeshellarg((string) $cfg['database']), escapeshellarg($arquivo));
        $saida = null;
        $codigo = 0;
        exec($cmd, $saida, $codigo);
        if ($codigo !== 0 || ! filesize($arquivo)) {
            $this->error('Falha no mysqldump. Confira se ele está instalado no servidor.');

            return 1;
        }
    }
    $todos = glob($pasta.'/torque-*');
    rsort($todos);
    foreach (array_slice($todos, (int) $this->option('manter')) as $velho) {
        unlink($velho);
    }
    $this->info('Backup: '.$arquivo.' ('.round(filesize($arquivo) / 1024).' KB)');

    return 0;
})->purpose('Faz backup do banco de dados');

/*
 * Desliga a verificação em duas etapas de um usuário que perdeu o celular e os códigos de recuperação.
 */
Artisan::command('torque:desativar-2-etapas {email}', function () {
    $u = User::where('email', strtolower($this->argument('email')))->first();
    if (! $u) {
        $this->error('Usuário não encontrado.');

        return 1;
    }
    $u->forceFill(['dois_fatores_segredo' => null, 'dois_fatores_ativo_em' => null, 'dois_fatores_recuperacao' => null])->save();
    \Illuminate\Support\Facades\Log::warning('2 etapas desativadas pelo servidor', ['user' => $u->id]);
    $this->info('Verificação em duas etapas desativada. No próximo acesso, ele configura de novo.');

    return 0;
})->purpose('Desativa a verificação em duas etapas de um usuário');

Artisan::command('torque:cnh-expurgar {--empresa= : Processa apenas a empresa informada} {--apply : Executa as exclusões (sem esta opção apenas simula)} {--aplicar-limite-tecnico : Permite usar o limite técnico em empresa sem política definida}', function (\App\Services\Cnh\ExpurgoCnh $expurgo) {
    // F-247-04/TORQUE-CNH-249: o expurgo permanece desligado por padrão. A chave
    // CNH_EXPURGO_ATIVO=true só deve ser ligada depois da política aprovada; sem
    // ela, nem a simulação executa.
    if (! config('cnh.expurgo_ativo', false)) {
        $this->line('Expurgo desativado nesta etapa (CNH_EXPURGO_ATIVO=false): nada foi expurgado.');

        return 0;
    }

    $empresa = $this->option('empresa');
    if ($empresa !== null && (! ctype_digit((string) $empresa) || ! Empresa::whereKey((int) $empresa)->exists())) {
        $this->error("Empresa {$empresa} não encontrada. Nada foi expurgado.");

        return 1;
    }

    $aplicar = (bool) $this->option('apply');
    $resultado = $expurgo->executar(
        $aplicar,
        $empresa === null ? null : (int) $empresa,
        (bool) $this->option('aplicar-limite-tecnico')
    );

    foreach ($resultado['empresas'] as $linha) {
        $rotulo = sprintf('Empresa %d (%s)', $linha['empresa_id'], $linha['nome']);
        if ($linha['motivo'] === 'politica_nao_definida') {
            $this->line("{$rotulo}: política de retenção não definida; nada foi expurgado. Use --aplicar-limite-tecnico para aplicar o limite técnico de {$linha['dias']} dia(s).");

            continue;
        }
        $this->line(sprintf(
            '%s: retenção %d dia(s) [origem: %s]; corte %s; antigos %d (elegíveis %d, preservados %d); recentes %d; removidos %d [%s]',
            $rotulo,
            $linha['dias'],
            $linha['origem'],
            $linha['limite'],
            $linha['antigos'],
            $linha['elegiveis'],
            $linha['preservados'],
            $linha['recentes'],
            $linha['removidos'],
            $linha['situacao']
        ));
    }

    if ($aplicar) {
        $this->info("Expurgo aplicado: {$resultado['removidos']} registro(s) removido(s).");
    } else {
        $this->info("Expurgo em simulação: {$resultado['elegiveis']} registro(s) elegível(is); nada foi excluído. Use --apply para executar.");
    }

    return 0;
})->purpose('Expurga evidências de consulta conforme o prazo de retenção de cada empresa');

/*
 * Autoteste do módulo CNH: ambiente, catálogo, cinco dimensões e adaptadores.
 * Não usa rede real (preventStrayRequests) e não grava no banco.
 */
Artisan::command('torque:cnh-autoteste', function () {
    $falhas = 0;
    $checar = function (bool $ok, string $descricao) use (&$falhas) {
        $this->line(($ok ? 'PASS  ' : 'FAIL  ').$descricao);
        $falhas += $ok ? 0 : 1;
    };

    Http::preventStrayRequests();

    $catalogo = app(\App\Services\Cnh\CatalogoCnh::class);
    $avaliacao = app(\App\Services\Cnh\AvaliacaoCnh::class);
    $senatran = app(\App\Services\Cnh\SenatranProvider::class);
    $ambienteOriginal = config('cnh.ambiente');

    $this->line('Ambiente configurado: '.config('cnh.ambiente'));
    foreach (array_keys(\App\Services\Cnh\CatalogoCnh::FORNECEDORES) as $codigo) {
        $this->line(sprintf('  %-12s %s', $codigo, $catalogo->implementado($codigo) ? 'conector implementado' : 'pendente (sem conector)'));
    }
    $checar($catalogo->implementado('senatran') && $catalogo->implementado('vio_serpro'), 'Catálogo marca senatran e vio_serpro como implementados');
    $checar(! $catalogo->implementado('minha_cnh') && ! $catalogo->implementado('vio_privado'), 'Catálogo não anuncia conector inexistente');

    $completo = ['categoria' => 'B', 'validade' => now()->addYear()->format('Y-m-d'), 'situacao' => 'Ativa', 'impedimentos' => 0];
    $producao = $avaliacao->avaliar('senatran', $completo, ['ambiente' => 'producao', 'autenticidade' => 'oficial', 'categorias_veiculo' => ['B']]);
    $checar($producao['liberacao'] === 'producao' && $producao['autenticidade'] === 'oficial' && $producao['resultado'] === 'conferido', 'SENATRAN em produção com dados completos libera a operação');
    $homolog = $avaliacao->avaliar('senatran', $completo, ['ambiente' => 'homologacao', 'autenticidade' => 'oficial', 'categorias_veiculo' => ['B']]);
    $checar($homolog['liberacao'] === 'homologacao' && $homolog['autenticidade'] === 'oficial_homologacao', 'SENATRAN em homologação não libera a operação');
    $vencida = $avaliacao->avaliar('senatran', ['categoria' => 'B', 'validade' => now()->subDay()->format('Y-m-d'), 'situacao' => 'Ativa', 'impedimentos' => 0], ['ambiente' => 'producao', 'categorias_veiculo' => ['B']]);
    $checar($vencida['resultado'] === 'divergente' && $vencida['liberacao'] === 'nao_liberado', 'Validade vencida gera divergência e bloqueia');
    $categoria = $avaliacao->avaliar('senatran', $completo, ['ambiente' => 'producao', 'categorias_veiculo' => ['C']]);
    $checar($categoria['resultado'] === 'divergente', 'Categoria incompatível gera divergência');
    $impedido = $avaliacao->avaliar('senatran', ['categoria' => 'B', 'validade' => now()->addYear()->format('Y-m-d'), 'situacao' => 'Ativa', 'impedimentos' => 2], ['ambiente' => 'producao']);
    $checar($impedido['resultado'] === 'divergente', 'Impedimentos geram divergência');
    $documental = $avaliacao->avaliar('vio_serpro', ['documento_teste' => 'CNH Digital'], ['ambiente' => 'homologacao']);
    $checar($documental['autenticidade'] === 'documental' && $documental['liberacao'] !== 'producao', 'Leitura documental não é tratada como autenticidade oficial');

    // F-247-01: a oficialidade vem do adaptador, nunca do nome do fornecedor.
    $checar(app(\App\Services\Cnh\SenatranByok::class)->autenticidade() === 'oficial', 'Adaptador SENATRAN declara autenticidade oficial');
    $checar(app(\App\Services\Cnh\VioByok::class)->autenticidade() === 'documental', 'Adaptador Vio declara autenticidade documental');
    $checar($catalogo->fonte('senatran') === 'oficial', 'Catálogo reconhece somente SENATRAN como fonte oficial');
    $checar($catalogo->fonte('minha_cnh') === 'documental' && $catalogo->fonte('vio_privado') === 'documental', 'Fornecedores sem conector nunca são oficiais');
    $minhaCnh = $avaliacao->avaliar('minha_cnh', $completo, ['ambiente' => 'producao', 'autenticidade' => 'oficial', 'categorias_veiculo' => ['B']]);
    $checar($minhaCnh['autenticidade'] !== 'oficial' && $minhaCnh['liberacao'] !== 'producao', 'minha_cnh não consegue declarar autenticidade oficial');
    $vioPrivado = $avaliacao->avaliar('vio_privado', ['documento_teste' => 'CNH Digital'], ['ambiente' => 'producao', 'autenticidade' => 'oficial']);
    $checar($vioPrivado['autenticidade'] === 'documental' && $vioPrivado['liberacao'] !== 'producao', 'vio_privado não consegue declarar autenticidade oficial');
    $vioOficial = $avaliacao->avaliar('vio_serpro', ['documento_teste' => 'CNH Digital'], ['ambiente' => 'producao', 'autenticidade' => 'oficial']);
    $checar($vioOficial['autenticidade'] === 'documental' && $vioOficial['liberacao'] !== 'producao', 'vio_serpro rebaixa declaração oficial para documental');
    $declaracaoInvalida = $avaliacao->avaliar('senatran', $completo, ['ambiente' => 'producao', 'autenticidade' => 'oficial_falso', 'categorias_veiculo' => ['B']]);
    $checar($declaracaoInvalida['autenticidade'] !== 'oficial' && $declaracaoInvalida['liberacao'] !== 'producao', 'Declaração de autenticidade desconhecida é fail-closed');
    $checar(array_keys($producao['dimensoes']) === array_keys(\App\Services\Cnh\AvaliacaoCnh::DIMENSOES), 'Avaliação devolve as cinco dimensões na ordem esperada');

    $urlProducao = trim((string) config('cnh.endpoints.senatran.producao'));
    config(['cnh.ambiente' => 'producao']);
    if ($urlProducao === '') {
        $recusou = null;
        try {
            $senatran->consultar(['certificado' => '/dev/null', 'chave' => '/dev/null', 'cpf_operador' => '52998224725'], '52998224725', '12345678901');
        } catch (\DomainException $erro) {
            $recusou = $erro->getMessage();
        }
        $checar(is_string($recusou) && str_contains($recusou, 'não está configurado'), 'Sem CNH_SENATRAN_URL_PRODUCAO a consulta é recusada antes de qualquer envio');
        $checar(Http::recorded()->count() === 0, 'Nenhuma requisição HTTP foi enviada na recusa por falta de endpoint');
    } else {
        $checar($senatran->endpoint('producao') === rtrim($urlProducao, '/'), 'CNH_SENATRAN_URL_PRODUCAO configurada e resolvida');
    }
    config(['cnh.ambiente' => $ambienteOriginal]);

    $endpoint = '';
    try {
        $endpoint = $senatran->endpoint('homologacao');
        $checar(true, 'Endpoint de homologação da SENATRAN resolvido: '.$endpoint);
    } catch (\DomainException $erro) {
        $checar(false, 'Endpoint de homologação da SENATRAN: '.$erro->getMessage());
    }

    $chave = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr = $chave ? openssl_csr_new(['commonName' => 'autoteste.torque.local'], $chave, ['digest_alg' => 'sha256']) : false;
    $cert = $csr ? openssl_csr_sign($csr, null, $chave, 1, ['digest_alg' => 'sha256']) : false;
    $pemCert = '';
    $pemKey = '';
    $gerou = $cert && openssl_x509_export($cert, $pemCert) && openssl_pkey_export($chave, $pemKey);
    $checar((bool) $gerou, 'Certificado e chave de teste gerados em memória');

    if ($gerou && $endpoint !== '') {
        Http::fake([$endpoint.'/v1/condutores/*' => Http::response(['condutores' => [[
            'categoriaAtual' => 'B', 'dataValidadeCnh' => now()->addYear()->format('Y-m-d'),
            'descricaoSituacaoCnh' => 'Ativa', 'quantidadeOcorrenciasImpedimentos' => 0,
        ]]], 200)]);
        try {
            $bruto = app(\App\Services\Cnh\SenatranByok::class)->consultar(
                ['certificado_pem' => $pemCert, 'chave_pem' => $pemKey, 'cpf_operador' => '52998224725'],
                ['cpf' => '52998224725', 'registro' => '12345678901']
            );
            $av = $avaliacao->avaliar('senatran', $bruto, ['ambiente' => $bruto['ambiente'] ?? null, 'categorias_veiculo' => ['B']]);
            $checar($bruto['categoria'] === 'B' && $bruto['ambiente'] === 'homologacao', 'Adaptador SENATRAN interpreta a resposta simulada');
            $checar($av['resultado'] === 'conferido' && $av['liberacao'] === 'homologacao', 'Resposta simulada confere, mas continua sem liberar em homologação');
        } catch (\Throwable $erro) {
            $checar(false, 'Adaptador SENATRAN: '.get_class($erro).' - '.$erro->getMessage());
        }
    }

    $vioBase = '';
    try {
        $vioBase = app(\App\Services\Cnh\VioByok::class)->baseUrl('homologacao');
        $checar(true, 'Endpoint do SERPRO/Vio resolvido: '.$vioBase);
    } catch (\DomainException $erro) {
        $checar(false, 'Endpoint do SERPRO/Vio: '.$erro->getMessage());
    }
    if ($vioBase !== '') {
        Http::fake([
            $vioBase.(string) config('cnh.endpoints.vio.token', '/token') => Http::response(['access_token' => 'token-de-teste'], 200),
            $vioBase.(string) config('cnh.endpoints.vio.decode', '/viodec-trial/v1/decode') => Http::response(['template' => ['name' => 'CNH Digital']], 200),
        ]);
        try {
            $brutoVio = app(\App\Services\Cnh\VioByok::class)->consultar(['consumer_key' => 'chave', 'consumer_secret' => 'segredo'], ['qr' => 'BINARIO-DE-TESTE']);
            $avVio = $avaliacao->avaliar('vio_serpro', $brutoVio, ['ambiente' => $brutoVio['ambiente'] ?? null]);
            $checar($brutoVio['documento_teste'] === 'CNH Digital', 'Adaptador Vio autentica e decodifica com resposta simulada');
            $checar($avVio['autenticidade'] === 'documental' && $avVio['liberacao'] !== 'producao', 'Vio nunca libera operação de produção');
        } catch (\Throwable $erro) {
            $checar(false, 'Adaptador Vio: '.get_class($erro).' - '.$erro->getMessage());
        }
    }

    config(['cnh.ambiente' => $ambienteOriginal]);
    $this->newLine();
    if ($falhas === 0) {
        $this->info('AUTOTESTE CNH: PASS - nenhuma consulta real, nenhuma escrita no banco.');

        return 0;
    }
    $this->error('AUTOTESTE CNH: '.$falhas.' falha(s).');

    return 1;
})->purpose('Autoteste do módulo CNH: ambiente, catálogo, dimensões e adaptadores (sem rede real e sem escrita no banco)');

Schedule::command('torque:cnh-expurgar')->dailyAt('03:25')->withoutOverlapping();
Schedule::command('torque:assinaturas')->dailyAt('03:10')->withoutOverlapping();
Schedule::command('torque:backup')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('torque:notas')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('torque:lembretes')->dailyAt('09:00')->withoutOverlapping();
