<?php

use App\Models\Empresa;
use App\Models\Loja;
use App\Models\User;
use App\Support\Documento;
use App\Support\Perfil;
use App\Support\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
