<?php

namespace Tests;

use App\Models\Empresa;
use App\Models\Loja;
use App\Models\User;
use App\Support\Perfil;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        Tenant::usar(null);
        parent::tearDown();
    }

    /** Cria empresa + loja + usuário com o perfil pedido. */
    protected function novaEmpresa(string $nome = 'Oficina Teste', Perfil $perfil = Perfil::Dono, ?string $email = null): User
    {
        $empresa = Empresa::create(['nome_fantasia' => $nome]);

        return Tenant::com($empresa->id, function () use ($empresa, $perfil, $email) {
            $loja = Loja::create(['nome' => 'Matriz']);

            return User::create([
                'empresa_id' => $empresa->id, 'loja_id' => $loja->id, 'name' => 'Usuário '.$perfil->value,
                'email' => $email ?? $perfil->value.'.'.$empresa->id.'@teste.com.br',
                'perfil' => $perfil, 'ativo' => true, 'password' => 'senha12345',
            ]);
        });
    }

    protected function usuarioNaMesmaEmpresa(User $de, Perfil $perfil): User
    {
        return Tenant::com($de->empresa_id, fn () => User::create([
            'empresa_id' => $de->empresa_id, 'loja_id' => $de->loja_id, 'name' => 'Usuário '.$perfil->value,
            'email' => $perfil->value.'.'.$de->empresa_id.'.'.uniqid().'@teste.com.br',
            'perfil' => $perfil, 'ativo' => true, 'password' => 'senha12345',
        ]));
    }
}
