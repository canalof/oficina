<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\Veiculo;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteVeiculoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadastra_cliente_com_cpf_valido_e_guarda_so_numeros(): void
    {
        $u = $this->novaEmpresa();

        $this->actingAs($u)->post('/clientes', [
            'nome' => 'Maria Teste', 'cpf_cnpj' => '529.982.247-25', 'whatsapp' => '(19) 99999-0000',
        ])->assertRedirect();

        $c = Tenant::com($u->empresa_id, fn () => Cliente::first());
        $this->assertSame('52998224725', $c->cpf_cnpj);
        $this->assertSame('PF', $c->tipo_pessoa);
    }

    public function test_rejeita_cpf_invalido_e_duplicado_na_mesma_empresa(): void
    {
        $u = $this->novaEmpresa();
        $this->actingAs($u);

        $this->post('/clientes', ['nome' => 'A', 'cpf_cnpj' => '123.456.789-00'])->assertSessionHasErrors('cpf_cnpj');

        $this->post('/clientes', ['nome' => 'A', 'cpf_cnpj' => '52998224725']);
        $this->post('/clientes', ['nome' => 'B', 'cpf_cnpj' => '529.982.247-25'])->assertSessionHasErrors('cpf_cnpj');
    }

    public function test_placa_ja_cadastrada_no_formato_antigo_bloqueia_a_versao_mercosul(): void
    {
        $u = $this->novaEmpresa();
        Tenant::com($u->empresa_id, fn () => Veiculo::create(['placa' => 'ABC1234', 'modelo' => 'Gol']));

        $this->actingAs($u)->post('/veiculos', ['placa' => 'ABC1C34', 'modelo' => 'Gol'])
            ->assertSessionHasErrors('placa');
    }

    public function test_cadastra_veiculo_normalizando_a_placa(): void
    {
        $u = $this->novaEmpresa();
        $c = Tenant::com($u->empresa_id, fn () => Cliente::create(['nome' => 'Dono']));

        $this->actingAs($u)->post('/veiculos', [
            'placa' => 'ftr-4e21', 'cliente_id' => $c->id, 'marca' => 'Hyundai', 'modelo' => 'HB20', 'km_atual' => '48.210',
        ])->assertRedirect();

        $v = Tenant::com($u->empresa_id, fn () => Veiculo::first());
        $this->assertSame('FTR4E21', $v->placa);
        $this->assertSame(48210, (int) $v->km_atual);
        $this->assertSame($c->id, $v->cliente_id);
        $this->assertSame(1, Tenant::com($u->empresa_id, fn () => $v->donos()->count()));
    }

    public function test_nao_aceita_cliente_de_outra_empresa_como_dono(): void
    {
        $u = $this->novaEmpresa('A');
        $outra = $this->novaEmpresa('B');
        $alheio = Tenant::com($outra->empresa_id, fn () => Cliente::create(['nome' => 'Alheio']));

        $this->actingAs($u)->post('/veiculos', ['placa' => 'AAA1A11', 'modelo' => 'Uno', 'cliente_id' => $alheio->id])
            ->assertSessionHasErrors('cliente_id');
    }

    public function test_transferir_veiculo_fecha_o_dono_anterior(): void
    {
        $u = $this->novaEmpresa();
        [$v, $novo] = Tenant::com($u->empresa_id, function () {
            $antigo = Cliente::create(['nome' => 'Antigo']);
            $v = Veiculo::create(['placa' => 'ABC1234', 'modelo' => 'Gol', 'cliente_id' => $antigo->id]);
            $v->donos()->create(['cliente_id' => $antigo->id, 'inicio' => now()->subYear()->toDateString()]);

            return [$v, Cliente::create(['nome' => 'Novo'])];
        });

        $this->actingAs($u)->post("/veiculos/{$v->id}/transferir", ['cliente_id' => $novo->id])->assertRedirect();

        Tenant::com($u->empresa_id, function () use ($v, $novo) {
            $v->refresh();
            $this->assertSame($novo->id, $v->cliente_id);
            $donos = $v->donos()->reorder('id')->get();
            $this->assertCount(2, $donos);
            $this->assertNotNull($donos[0]->fim);
            $this->assertNull($donos[1]->fim);
        });
    }

    public function test_alteracoes_ficam_na_auditoria_com_o_usuario(): void
    {
        $u = $this->novaEmpresa();
        $this->actingAs($u)->post('/clientes', ['nome' => 'Auditado']);
        $c = Tenant::com($u->empresa_id, fn () => Cliente::first());
        $this->put("/clientes/{$c->id}", ['nome' => 'Auditado 2']);

        $log = Auditoria::where('modelo', 'Cliente')->where('acao', 'alterou')->first();
        $this->assertNotNull($log);
        $this->assertSame($u->id, $log->user_id);
        $this->assertSame($u->empresa_id, $log->empresa_id);
        $this->assertSame('Auditado 2', $log->depois['nome']);
    }

    public function test_busca_de_clientes_por_nome_documento_e_placa(): void
    {
        $u = $this->novaEmpresa();
        Tenant::com($u->empresa_id, function () {
            $c = Cliente::create(['nome' => 'Joana Prado', 'cpf_cnpj' => '52998224725']);
            Veiculo::create(['placa' => 'QWE1R23', 'modelo' => 'Onix', 'cliente_id' => $c->id]);
            Cliente::create(['nome' => 'Outro Nome']);
        });
        $this->actingAs($u);

        $this->get('/clientes?busca=joana')->assertSee('Joana Prado')->assertDontSee('Outro Nome');
        $this->get('/clientes?busca=529.982')->assertSee('Joana Prado')->assertDontSee('Outro Nome');
        $this->get('/veiculos?busca=qwe1r23')->assertSee('Onix');
    }
}
