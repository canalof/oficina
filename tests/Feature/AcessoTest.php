<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Support\Perfil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcessoTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_vai_para_o_login(): void
    {
        $this->get('/')->assertRedirect('/entrar');
        $this->get('/entrar')->assertOk()->assertSee('Entrar');
    }

    public function test_login_com_senha_certa_e_errada(): void
    {
        $u = $this->novaEmpresa(email: 'dono@oficina.com.br');

        $this->post('/entrar', ['email' => 'dono@oficina.com.br', 'password' => 'errada123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/entrar', ['email' => 'DONO@oficina.com.br', 'password' => 'senha12345'])
            ->assertRedirect('/');
        $this->assertAuthenticatedAs($u);
    }

    public function test_usuario_inativo_nao_entra(): void
    {
        $u = $this->novaEmpresa(email: 'inativo@oficina.com.br');
        $u->update(['ativo' => false]);

        $this->post('/entrar', ['email' => 'inativo@oficina.com.br', 'password' => 'senha12345']);
        $this->assertGuest();
    }

    public function test_empresa_desativada_derruba_a_sessao(): void
    {
        $u = $this->novaEmpresa();
        Empresa::whereKey($u->empresa_id)->update(['ativo' => false]);

        $this->actingAs($u)->get('/')->assertRedirect('/entrar');
        $this->assertGuest();
    }

    public function test_mecanico_nao_gerencia_usuarios_nem_empresa(): void
    {
        $dono = $this->novaEmpresa();
        $mec = $this->usuarioNaMesmaEmpresa($dono, Perfil::Mecanico);

        $this->actingAs($mec);
        $this->get('/')->assertOk();
        $this->get('/usuarios')->assertForbidden();
        $this->get('/empresa')->assertForbidden();
        $this->post('/clientes', ['nome' => 'Não pode'])->assertForbidden();

        $this->actingAs($dono);
        $this->get('/usuarios')->assertOk();
        $this->get('/empresa')->assertOk();
    }

    public function test_dono_nao_pode_tirar_o_proprio_perfil_de_dono(): void
    {
        $dono = $this->novaEmpresa();

        $this->actingAs($dono)->put("/usuarios/{$dono->id}", [
            'name' => 'Eu', 'email' => $dono->email, 'perfil' => Perfil::Mecanico->value, 'ativo' => '1',
        ]);

        $this->assertSame(Perfil::Dono, $dono->fresh()->perfil);
    }

    public function test_dono_nao_edita_usuario_de_outra_empresa(): void
    {
        $dono = $this->novaEmpresa('A');
        $outro = $this->novaEmpresa('B');

        $this->actingAs($dono)->get("/usuarios/{$outro->id}/edit")->assertNotFound();
    }

    public function test_paginas_principais_abrem(): void
    {
        $this->actingAs($this->novaEmpresa());

        foreach (['/', '/recepcao', '/clientes', '/clientes/create', '/veiculos', '/veiculos/create', '/usuarios', '/usuarios/create', '/empresa', '/conta/senha'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
