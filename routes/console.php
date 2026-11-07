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
Artisan::command('torque:empresa {nome_fantasia} {email} {--cnpj=} {--nome=Responsável} {--senha=}', function () {
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
        $empresa = Empresa::create(['nome_fantasia' => $this->argument('nome_fantasia'), 'cnpj' => $cnpj]);
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
                if ($l->cliente?->aceita_whatsapp && $whatsapp->enviar($empresa, $l->cliente->whatsapp ?: $l->cliente->telefone, $l->mensagem($empresa->nome_fantasia))) {
                    $l->update(['status' => 'enviado', 'enviado_em' => now()]);
                    $enviados++;
                }
            }
            $amanha = \App\Models\Agendamento::whereBetween('inicio', [today()->addDay(), today()->addDay()->endOfDay()])
                ->whereIn('status', ['agendado', 'confirmado'])->whereNull('lembrete_enviado_em')->get();
            foreach ($amanha as $a) {
                $txt = 'Olá, '.explode(' ', trim($a->nome))[0].'! Lembrando do seu horário amanhã às '.$a->inicio->format('H:i')
                    .' na '.$empresa->nome_fantasia.' ('.$a->servico.'). Se precisar remarcar, é só responder.';
                if ($whatsapp->enviar($empresa, $a->telefone, $txt)) {
                    $a->update(['lembrete_enviado_em' => now()]);
                    $enviados++;
                }
            }
        });
    }
    $this->info("{$enviados} mensagem(ns) enviada(s).");
})->purpose('Envia lembretes de revisão e de agenda pelo WhatsApp');

Schedule::command('torque:notas')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('torque:lembretes')->dailyAt('09:00')->withoutOverlapping();
