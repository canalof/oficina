<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\PlacaConsulta;
use App\Models\Veiculo;
use App\Services\Placa\ConsultaDePlacaNet;
use App\Services\Placa\ConsultaPlacaIndisponivel;
use App\Services\Placa\ProvedorPlaca;
use App\Support\Perfil;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class ConsultaPlacaTest extends TestCase
{
    use RefreshDatabase;

    private function usarApiReal(): void
    {
        config(['placa.provedor' => 'consultadeplaca', 'placa.consultadeplaca.chave' => 'chave-teste']);
        $this->app->forgetInstance(ProvedorPlaca::class);
        Http::preventStrayRequests();
        Sleep::fake();
    }

    private function respostaEncontrada(): array
    {
        return [
            'result' => 'found',
            'vehicle' => [
                'brand' => 'VOLKSWAGEN', 'model' => 'GOL', 'version' => '1.0 MPI', 'manufacture_year' => 2018, 'model_year' => '2019',
                'color' => 'PRATA', 'fuel' => 'FLEX', 'city' => 'CAMPINAS', 'state' => 'sp', 'theft' => false,
                'fipe' => ['code' => '005340-6', 'amount' => '48900.00'],
            ],
        ];
    }

    public function test_placa_ja_cadastrada_abre_a_ficha_sem_gastar_consulta(): void
    {
        $this->usarApiReal();
        Http::fake();
        $u = $this->novaEmpresa();
        Tenant::com($u->empresa_id, function () {
            $c = Cliente::create(['nome' => 'Mariana']);
            Veiculo::create(['placa' => 'FTR4E21', 'modelo' => 'HB20', 'cliente_id' => $c->id]);
        });

        // digitada no formato antigo equivalente
        $this->actingAs($u)->getJson('/recepcao/placa?placa=ftr-4421')
            ->assertOk()->assertJson(['status' => 'cadastrado'])
            ->assertJsonPath('html', fn ($h) => str_contains($h, 'Mariana'));

        Http::assertNothingSent();
    }

    public function test_placa_invalida(): void
    {
        $this->actingAs($this->novaEmpresa())->getJson('/recepcao/placa?placa=XX12')
            ->assertStatus(422)->assertJson(['status' => 'invalida']);
    }

    public function test_placa_nova_consulta_a_api_com_os_cabecalhos_certos_e_reaproveita_depois(): void
    {
        $this->usarApiReal();
        Http::fake(['consultadeplaca.net/*' => Http::response($this->respostaEncontrada(), 201)]);
        $u = $this->novaEmpresa();
        $this->actingAs($u);

        $r = $this->getJson('/recepcao/placa?placa=BRA2E19')->assertOk()->assertJson(['status' => 'novo']);
        $this->assertStringContainsString('Volkswagen', $r->json('html'));
        $this->assertStringContainsString('48.900', $r->json('html'));

        Http::assertSent(fn (Request $req) => $req->url() === 'https://consultadeplaca.net/api/v1/lookups'
            && $req->method() === 'POST'
            && $req['plate'] === 'BRA2E19'
            && $req->hasHeader('Authorization', 'Bearer chave-teste')
            && $req->hasHeader('Idempotency-Key'));

        // segunda vez: vem do histórico, sem nova cobrança
        $this->getJson('/recepcao/placa?placa=BRA2E19')->assertOk();
        Http::assertSentCount(1);

        $consultas = Tenant::com($u->empresa_id, fn () => PlacaConsulta::all());
        $this->assertCount(1, $consultas);
        $this->assertSame('encontrado', $consultas[0]->status);
        $this->assertSame(7, $consultas[0]->custo_centavos);
    }

    public function test_reaproveitamento_respeita_o_prazo_e_a_empresa(): void
    {
        $this->usarApiReal();
        Http::fake(['consultadeplaca.net/*' => Http::response($this->respostaEncontrada(), 201)]);
        $a = $this->novaEmpresa('A');
        $b = $this->novaEmpresa('B');

        $this->actingAs($a)->getJson('/recepcao/placa?placa=BRA2E19');
        $this->actingAs($b)->getJson('/recepcao/placa?placa=BRA2E19');
        Http::assertSentCount(2); // cada oficina paga a sua consulta

        $this->travel(31)->days();
        $this->actingAs($a)->getJson('/recepcao/placa?placa=BRA2E19');
        Http::assertSentCount(3);
    }

    public function test_dados_mapeados_do_provedor(): void
    {
        $this->usarApiReal();
        Http::fake(['consultadeplaca.net/*' => Http::response($this->respostaEncontrada(), 201)]);

        $d = $this->app->make(ConsultaDePlacaNet::class, ['chave' => 'x', 'url' => 'https://consultadeplaca.net/api/v1', 'custoCentavos' => 7])
            ->consultar('BRA2E19');

        $this->assertSame('Volkswagen', $d->marca);
        $this->assertSame('Gol', $d->modelo);
        $this->assertSame(2019, $d->anoModelo);
        $this->assertSame('SP', $d->uf);
        $this->assertSame(4890000, $d->fipeValorCentavos);
    }

    public function test_placa_nao_encontrada_e_saldo_acabado(): void
    {
        $this->usarApiReal();
        Http::fake(['consultadeplaca.net/*' => Http::sequence()
            ->push(['result' => 'not_found'], 201)
            ->push(['code' => 'insufficient_balance', 'message' => 'x'], 402)]);
        $u = $this->novaEmpresa();
        $this->actingAs($u);

        $this->getJson('/recepcao/placa?placa=ZZZ1A23')->assertOk()
            ->assertJsonPath('html', fn ($h) => str_contains($h, 'não encontrou dados'));

        $this->getJson('/recepcao/placa?placa=YYY1A23')->assertOk()
            ->assertJsonPath('html', fn ($h) => str_contains($h, 'Saldo da consulta de placa acabou'));

        $status = Tenant::com($u->empresa_id, fn () => PlacaConsulta::orderBy('id')->pluck('status')->all());
        $this->assertSame(['nao_encontrado', 'erro'], $status);
    }

    public function test_alerta_de_roubo_ou_furto(): void
    {
        $this->usarApiReal();
        $resp = $this->respostaEncontrada();
        $resp['vehicle']['theft'] = true;
        Http::fake(['consultadeplaca.net/*' => Http::response($resp, 201)]);

        $this->actingAs($this->novaEmpresa())->getJson('/recepcao/placa?placa=BRA2E19')
            ->assertJsonPath('html', fn ($h) => str_contains($h, 'roubo ou furto'));
    }

    public function test_quem_nao_tem_permissao_nao_gasta_consulta(): void
    {
        $this->usarApiReal();
        Http::fake();
        $dono = $this->novaEmpresa();
        $mec = $this->usuarioNaMesmaEmpresa($dono, Perfil::Mecanico);

        $this->actingAs($mec)->getJson('/recepcao/placa?placa=BRA2E19')->assertOk()->assertJson(['status' => 'novo']);
        Http::assertNothingSent();
    }

    public function test_falha_de_conexao_vira_aviso_amigavel(): void
    {
        $this->usarApiReal();
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));

        $this->expectException(ConsultaPlacaIndisponivel::class);
        $this->app->make(ConsultaDePlacaNet::class, ['chave' => 'x', 'url' => 'https://consultadeplaca.net/api/v1', 'custoCentavos' => 7])
            ->consultar('BRA2E19');
    }
}
