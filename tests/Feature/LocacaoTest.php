<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ContaReceber;
use App\Models\MobilidadeCondutor;
use App\Models\MobilidadeFrota;
use App\Models\MobilidadeReserva;
use App\Models\OrdemServico;
use App\Models\User;
use App\Models\Veiculo;
use App\Support\Perfil;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocacaoTest extends TestCase
{
    use RefreshDatabase;

    private function em(User $u, callable $fn): mixed
    {
        return Tenant::com($u->empresa_id, $fn);
    }

    private function comLocacao(string $nome = 'Auto Center Locação'): User
    {
        $u = $this->novaEmpresa($nome);
        $u->empresa->forceFill(['modulo_locacao' => true])->save();

        return $u->fresh();
    }

    /** Cliente, carro da frota e condutor da empresa do usuário. */
    private function base(User $u): array
    {
        return $this->em($u, fn () => [
            Cliente::create(['nome' => 'Bruno Alves', 'cpf_cnpj' => '52998224725']),
            MobilidadeFrota::create(['placa' => 'RES1A23', 'descricao' => 'Onix branco', 'km' => 10000, 'situacao' => 'disponivel']),
            MobilidadeCondutor::create(['nome' => 'Bruno Alves', 'categoria' => 'B', 'validade' => now()->addYear()->toDateString()]),
        ]);
    }

    private function osAberta(User $u, Cliente $c): OrdemServico
    {
        $v = $this->em($u, fn () => Veiculo::create(['placa' => 'OFI2B34', 'modelo' => 'HB20', 'cliente_id' => $c->id, 'km_atual' => 50000]));
        $this->actingAs($u)->post('/os', ['veiculo_id' => $v->id, 'cliente_id' => $c->id, 'km_entrada' => '50100']);

        return $this->em($u, fn () => OrdemServico::latest('id')->first());
    }

    private function periodo(): array
    {
        return ['inicio' => now()->subMinutes(5)->format('Y-m-d\TH:i'), 'fim' => now()->addDays(2)->subMinutes(5)->format('Y-m-d\TH:i')];
    }

    public function test_modulo_desligado_esconde_e_bloqueia_a_locacao(): void
    {
        $u = $this->novaEmpresa();
        $this->assertFalse($u->can('locacao.ver'));

        $this->actingAs($u)->get('/locacao')->assertForbidden();
        $this->post('/locacao/frota', ['placa' => 'ABC1D23', 'descricao' => 'Gol', 'km' => 1, 'situacao' => 'disponivel'])->assertForbidden();
        $this->get('/')->assertOk()->assertDontSee('Reservas e frota');

        // Condutores e retirada são da oficina e continuam disponíveis
        $this->get('/mobilidade')->assertOk()->assertSee('Condutores e retirada');

        $u->empresa->forceFill(['modulo_locacao' => true])->save();
        $this->actingAs($u->fresh())->get('/locacao')->assertOk()->assertSee('Novo aluguel')->assertSee('Novo carro reserva')->assertSee('Reservas e frota');
    }

    public function test_ligar_o_modulo_pela_tela_da_empresa(): void
    {
        $u = $this->novaEmpresa();
        $dados = ['nome_fantasia' => 'Oficina Teste', 'regime_tributario' => 1, 'cfop_padrao' => '5102', 'csosn_padrao' => '102',
            'item_lista_servico' => '14.01', 'aliquota_iss' => '2', 'fiscal_ambiente' => 'homologacao', 'agenda_capacidade' => 5,
            'orcamento_validade_dias' => 10, 'modulo_locacao' => '1'];
        $this->actingAs($u)->put('/empresa', $dados)->assertSessionHasNoErrors();
        $this->assertTrue($u->empresa->fresh()->modulo_locacao);
    }

    public function test_aluguel_sem_os_gera_titulo_na_categoria_locacao(): void
    {
        $u = $this->comLocacao();
        [$cliente, $carro, $condutor] = $this->base($u);

        $this->actingAs($u)->post('/locacao/reservas', [
            'modalidade' => 'aluguel', 'cliente_id' => $cliente->id, 'condutor_id' => $condutor->id,
            'tipo' => 'frota', 'frota_id' => $carro->id, 'diaria' => '150,00', 'caucao' => '500,00',
        ] + $this->periodo())->assertSessionHas('ok', 'Aluguel registrado.');

        $r = $this->em($u, fn () => MobilidadeReserva::firstOrFail());
        $this->assertSame(['aluguel', null, $cliente->id, 15000, 50000], [$r->modalidade, $r->ordem_servico_id, $r->cliente_id, $r->diaria_centavos, $r->caucao_centavos]);

        $saida = ['km' => 10000, 'combustivel' => 4, 'vistoria' => 'Sem avarias', 'aceite_nome' => 'Bruno Alves'];
        $this->post("/locacao/reservas/{$r->id}/entregar", $saida)->assertSessionHas('ok');
        $this->post("/locacao/reservas/{$r->id}/devolver", ['km' => 10400, 'taxas' => '30,00'] + $saida)->assertSessionHas('ok');

        $titulo = $this->em($u, fn () => ContaReceber::firstOrFail());
        $this->assertSame(['locacao', $cliente->id, null, 2 * 15000 + 3000], [$titulo->categoria, $titulo->cliente_id, $titulo->ordem_servico_id, $titulo->valor_centavos]);
        $this->assertStringContainsString('aluguel', $titulo->descricao);
        $this->assertSame(10400, $this->em($u, fn () => $carro->fresh()->km));

        $this->get('/locacao?situacao=encerradas&modalidade=aluguel')->assertOk()->assertSee('Bruno Alves')->assertSee('Devolvida');
    }

    public function test_aluguel_nao_aceita_os_nem_locadora_parceira(): void
    {
        $u = $this->comLocacao();
        [$cliente, $carro, $condutor] = $this->base($u);
        $os = $this->osAberta($u, $cliente);

        $this->actingAs($u)->post('/locacao/reservas', [
            'modalidade' => 'aluguel', 'cliente_id' => $cliente->id, 'ordem_servico_id' => $os->id, 'condutor_id' => $condutor->id,
            'tipo' => 'frota', 'frota_id' => $carro->id,
        ] + $this->periodo())->assertSessionHasErrors('ordem_servico_id');

        $this->post('/locacao/reservas', [
            'modalidade' => 'aluguel', 'cliente_id' => $cliente->id, 'condutor_id' => $condutor->id,
            'tipo' => 'parceira', 'parceira' => 'Localiza',
        ] + $this->periodo())->assertSessionHas('erro', 'Aluguel usa um carro da frota própria.');

        $this->assertSame(0, $this->em($u, fn () => MobilidadeReserva::count()));
    }

    public function test_carro_reserva_exige_os_e_pega_o_cliente_dela(): void
    {
        $u = $this->comLocacao();
        [$cliente, $carro, $condutor] = $this->base($u);

        $this->actingAs($u)->post('/locacao/reservas', [
            'modalidade' => 'carro_reserva', 'condutor_id' => $condutor->id, 'tipo' => 'frota', 'frota_id' => $carro->id,
        ] + $this->periodo())->assertSessionHasErrors('ordem_servico_id');

        $os = $this->osAberta($u, $cliente);
        $this->post('/locacao/reservas', [
            'modalidade' => 'carro_reserva', 'ordem_servico_id' => $os->id, 'condutor_id' => $condutor->id,
            'tipo' => 'frota', 'frota_id' => $carro->id,
        ] + $this->periodo())->assertSessionHas('ok', 'Carro reserva registrado.');

        $r = $this->em($u, fn () => MobilidadeReserva::firstOrFail());
        $this->assertSame(['carro_reserva', $os->id, $cliente->id, 0], [$r->modalidade, $r->ordem_servico_id, $r->cliente_id, $r->diaria_centavos]);

        // Cortesia: devolução sem diária não gera título
        $saida = ['km' => 10000, 'combustivel' => 2, 'vistoria' => 'Ok', 'aceite_nome' => 'Bruno'];
        $this->post("/locacao/reservas/{$r->id}/entregar", $saida);
        $this->post("/locacao/reservas/{$r->id}/devolver", ['km' => 10050] + $saida)->assertSessionHas('ok');
        $this->assertSame(0, $this->em($u, fn () => ContaReceber::count()));

        // Botão na OS leva para a Locação filtrada
        $this->get("/os/{$os->id}")->assertSee('Carro reserva');
        $this->get("/locacao?os={$os->id}&situacao=encerradas")->assertOk()->assertSee('OS '.$os->numeroFormatado());
    }

    public function test_carro_reserva_cobrado_liga_o_titulo_a_os(): void
    {
        $u = $this->comLocacao();
        [$cliente, $carro, $condutor] = $this->base($u);
        $os = $this->osAberta($u, $cliente);

        $this->actingAs($u)->post('/locacao/reservas', [
            'modalidade' => 'carro_reserva', 'ordem_servico_id' => $os->id, 'condutor_id' => $condutor->id,
            'tipo' => 'frota', 'frota_id' => $carro->id, 'diaria' => '80',
        ] + $this->periodo());
        $r = $this->em($u, fn () => MobilidadeReserva::firstOrFail());
        $saida = ['km' => 10000, 'combustivel' => 2, 'vistoria' => 'Ok', 'aceite_nome' => 'Bruno'];
        $this->post("/locacao/reservas/{$r->id}/entregar", $saida);
        $this->post("/locacao/reservas/{$r->id}/devolver", ['km' => 10050] + $saida);

        $titulo = $this->em($u, fn () => ContaReceber::firstOrFail());
        $this->assertSame(['locacao', $os->id, $cliente->id, 16000], [$titulo->categoria, $titulo->ordem_servico_id, $titulo->cliente_id, $titulo->valor_centavos]);
    }

    public function test_isolamento_entre_empresas(): void
    {
        $a = $this->comLocacao('Locadora A');
        $b = $this->comLocacao('Locadora B');
        [$clienteA, $carroA, $condutorA] = $this->base($a);
        [, , $condutorB] = $this->em($b, fn () => [null, null, MobilidadeCondutor::create(['nome' => 'Carla', 'categoria' => 'B'])]);

        $this->actingAs($a)->post('/locacao/reservas', [
            'modalidade' => 'aluguel', 'cliente_id' => $clienteA->id, 'condutor_id' => $condutorA->id,
            'tipo' => 'frota', 'frota_id' => $carroA->id,
        ] + $this->periodo());
        $reservaA = $this->em($a, fn () => MobilidadeReserva::firstOrFail());

        // B não usa cliente, carro nem reserva de A
        $this->actingAs($b)->post('/locacao/reservas', [
            'modalidade' => 'aluguel', 'cliente_id' => $clienteA->id, 'condutor_id' => $condutorB->id,
            'tipo' => 'frota', 'frota_id' => $carroA->id,
        ] + $this->periodo())->assertSessionHasErrors(['cliente_id', 'frota_id']);
        $this->post("/locacao/reservas/{$reservaA->id}/cancelar")->assertNotFound();
        $this->put("/locacao/frota/{$carroA->id}", ['placa' => 'RES1A23', 'descricao' => 'X', 'km' => 1, 'situacao' => 'inativo'])->assertNotFound();
        $this->get('/locacao')->assertOk()->assertDontSee('Bruno Alves')->assertDontSee('Onix branco');

        $this->assertSame('reservada', $this->em($a, fn () => $reservaA->fresh()->status));
    }

    public function test_mecanico_nao_ve_locacao_e_documentos_respeitam_o_modulo(): void
    {
        $u = $this->comLocacao();
        $mec = $this->usuarioNaMesmaEmpresa($u, Perfil::Mecanico);
        $this->actingAs($mec)->get('/locacao')->assertForbidden();

        // Com o módulo desligado, documentos de locação somem e os da retirada continuam
        $u->empresa->forceFill(['modulo_locacao' => false])->save();
        $this->actingAs($u->fresh())->get('/mobilidade/documentos')->assertOk()
            ->assertSee('Autorização de retirada')->assertDontSee('Contrato de locação');
    }
}
