<?php

use App\Models\Empresa;
use App\Models\Loja;
use App\Models\User;
use App\Support\Documento;
use App\Support\Perfil;
use App\Support\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
                if ($l->cliente?->aceita_whatsapp && $whatsapp->enviar($empresa, $l->cliente->whatsapp ?: $l->cliente->telefone, $l->mensagem($empresa->nome_fantasia), 'lembrete', $l->id)) {
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

Schedule::command('torque:assinaturas')->dailyAt('03:10')->withoutOverlapping();
Schedule::command('torque:backup')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('torque:notas')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('torque:lembretes')->dailyAt('09:00')->withoutOverlapping();
