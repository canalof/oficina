<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Veiculo;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/** Uma oficina nunca pode ver nem mexer nos dados de outra. */
class EmpresaIsolamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_consultas_so_enxergam_a_propria_empresa(): void
    {
        $a = $this->novaEmpresa('Oficina A');
        $b = $this->novaEmpresa('Oficina B');

        Tenant::com($a->empresa_id, fn () => Cliente::create(['nome' => 'Cliente da A']));
        Tenant::com($b->empresa_id, fn () => Cliente::create(['nome' => 'Cliente da B']));

        $this->assertSame(['Cliente da A'], Tenant::com($a->empresa_id, fn () => Cliente::pluck('nome')->all()));
        $this->assertSame(['Cliente da B'], Tenant::com($b->empresa_id, fn () => Cliente::pluck('nome')->all()));
    }

    public function test_sem_empresa_definida_nao_retorna_nada_e_nao_grava(): void
    {
        $a = $this->novaEmpresa();
        Tenant::com($a->empresa_id, fn () => Cliente::create(['nome' => 'X']));

        $this->assertSame(0, Cliente::count());

        $this->expectException(RuntimeException::class);
        Cliente::create(['nome' => 'Sem empresa']);
    }

    public function test_url_de_cliente_e_veiculo_de_outra_empresa_da_404(): void
    {
        $a = $this->novaEmpresa('Oficina A');
        $b = $this->novaEmpresa('Oficina B');
        [$cliente, $veiculo] = Tenant::com($b->empresa_id, function () {
            $c = Cliente::create(['nome' => 'Cliente da B']);

            return [$c, Veiculo::create(['cliente_id' => $c->id, 'placa' => 'BBB1B11', 'modelo' => 'Uno'])];
        });

        $this->actingAs($a);
        $this->get("/clientes/{$cliente->id}")->assertNotFound();
        $this->get("/veiculos/{$veiculo->id}")->assertNotFound();
        $this->put("/clientes/{$cliente->id}", ['nome' => 'Hackeado'])->assertNotFound();
        $this->delete("/veiculos/{$veiculo->id}")->assertNotFound();

        $this->actingAs($b);
        $this->get("/clientes/{$cliente->id}")->assertOk()->assertSee('Cliente da B');
    }

    public function test_mesma_placa_pode_existir_em_empresas_diferentes(): void
    {
        $a = $this->novaEmpresa('Oficina A');
        $b = $this->novaEmpresa('Oficina B');

        Tenant::com($a->empresa_id, fn () => Veiculo::create(['placa' => 'ABC1234', 'modelo' => 'Gol']));
        Tenant::com($b->empresa_id, fn () => Veiculo::create(['placa' => 'ABC1234', 'modelo' => 'Gol']));

        $this->assertSame(2, Veiculo::withoutGlobalScopes()->where('placa', 'ABC1234')->count());
    }
}
