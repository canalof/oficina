<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\OrdemServico;
use App\Models\Servico;
use App\Models\User;
use App\Models\Veiculo;
use App\Support\Dinheiro;
use App\Support\Perfil;
use App\Support\StatusOs;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrdemServicoTest extends TestCase
{
    use RefreshDatabase;

    private function carro(User $u, string $placa = 'ABC1D23'): Veiculo
    {
        return Tenant::com($u->empresa_id, function () use ($placa) {
            $c = Cliente::create(['nome' => 'Mariana Souza', 'whatsapp' => '(19) 99876-5432', 'aceita_whatsapp' => true]);

            return Veiculo::create(['placa' => $placa, 'modelo' => 'HB20', 'marca' => 'Hyundai', 'cliente_id' => $c->id, 'km_atual' => 40000]);
        });
    }

    /** Abre uma OS pela tela e devolve o model. */
    private function abrirOs(User $u, ?Veiculo $v = null): OrdemServico
    {
        $v ??= $this->carro($u);
        $this->actingAs($u)->post('/os', [
            'veiculo_id' => $v->id, 'cliente_id' => $v->cliente_id, 'km_entrada' => '41.250', 'combustivel' => 2,
            'defeito_relatado' => 'Barulho ao frear',
            'checklist' => ['vidros' => ['estado' => 'avaria', 'obs' => 'trinca'], 'pneus' => ['estado' => 'ok'], 'inventado' => ['estado' => 'ok']],
        ])->assertRedirect();

        return Tenant::com($u->empresa_id, fn () => OrdemServico::latest('id')->first());
    }

    private function item(User $u, OrdemServico $os, string $desc, string $valor, string $qtd = '1', string $tipo = 'servico'): void
    {
        $this->actingAs($u)->post("/os/{$os->id}/itens", ['tipo' => $tipo, 'descricao' => $desc, 'quantidade' => $qtd, 'valor' => $valor])
            ->assertSessionHasNoErrors();
    }

    private function recarregar(OrdemServico $os): OrdemServico
    {
        return Tenant::com($os->empresa_id, fn () => OrdemServico::with('itens')->find($os->id));
    }

    public function test_dinheiro_converte_formatos_brasileiros(): void
    {
        $this->assertSame(123456, Dinheiro::paraCentavos('1.234,56'));
        $this->assertSame(8990, Dinheiro::paraCentavos('R$ 89,90'));
        $this->assertSame(123400, Dinheiro::paraCentavos('1.234'));
        $this->assertSame(1050, Dinheiro::paraCentavos('10.50'));
        $this->assertSame(0, Dinheiro::paraCentavos(''));
        $this->assertSame('R$ 1.234,56', Dinheiro::formatar(123456));
    }

    public function test_abre_os_com_numero_por_empresa_checklist_limpo_e_km_atualizado(): void
    {
        $a = $this->novaEmpresa('A');
        $b = $this->novaEmpresa('B');

        $os1 = $this->abrirOs($a);
        $os2 = $this->abrirOs($a, $this->carro($a, 'XYZ9A87'));
        $osB = $this->abrirOs($b);

        $this->assertSame([1, 2, 1], [$os1->numero, $os2->numero, $osB->numero]);
        $this->assertSame(StatusOs::Orcamento, $os1->status);
        $this->assertSame($a->id, $os1->consultor_id);
        $this->assertSame(41250, $os1->km_entrada);
        $this->assertSame(['vidros', 'pneus'], array_keys($os1->checklist)); // item desconhecido descartado
        $this->assertSame(41250, Tenant::com($a->empresa_id, fn () => Veiculo::find($os1->veiculo_id)->km_atual));
        $this->assertSame(40, strlen($os1->token));
    }

    public function test_itens_somam_total_com_quantidade_e_desconto(): void
    {
        $u = $this->novaEmpresa();
        $os = $this->abrirOs($u);
        $this->item($u, $os, 'Troca de pastilhas', '90,00');
        $this->item($u, $os, 'Pastilha dianteira', '89,90', '2', 'peca');

        $this->assertSame(26980, $this->recarregar($os)->total_centavos);

        $this->put("/os/{$os->id}/desconto", ['desconto' => '9,80']);
        $this->assertSame(26000, $this->recarregar($os)->total_centavos);

        // desconto nunca passa do subtotal
        $this->put("/os/{$os->id}/desconto", ['desconto' => '9.999,00']);
        $this->assertSame(0, $this->recarregar($os)->total_centavos);
    }

    public function test_item_do_catalogo_usa_nome_e_preco_padrao(): void
    {
        $u = $this->novaEmpresa();
        $s = Tenant::com($u->empresa_id, fn () => Servico::create(['nome' => 'Alinhamento', 'preco_centavos' => 12000]));
        $os = $this->abrirOs($u);

        $this->actingAs($u)->post("/os/{$os->id}/itens", ['tipo' => 'servico', 'servico_id' => $s->id, 'quantidade' => '1', 'valor' => ''])
            ->assertSessionHasNoErrors();

        $item = $this->recarregar($os)->itens->first();
        $this->assertSame('Alinhamento', $item->descricao);
        $this->assertSame(12000, $item->total_centavos);
    }

    public function test_fluxo_completo_com_aprovacao_do_cliente_pelo_link(): void
    {
        $u = $this->novaEmpresa();
        $os = $this->abrirOs($u);
        $this->item($u, $os, 'Troca de pastilhas', '90,00');
        $this->item($u, $os, 'Troca de discos', '300,00');
        $os = $this->recarregar($os);

        // enviar pelo WhatsApp redireciona para o wa.me com o link do orçamento
        $r = $this->post("/os/{$os->id}/acao/enviar", ['whatsapp' => 1]);
        $this->assertStringStartsWith('https://wa.me/5519998765432', $r->headers->get('Location'));
        $this->assertStringContainsString(urlencode($os->linkAprovacao()), $r->headers->get('Location'));
        $this->assertSame(StatusOs::Aguardando, $this->recarregar($os)->status);

        // cliente, sem login, aprova só as pastilhas
        auth()->logout();
        $this->get('/orcamento/'.$os->token)->assertOk()->assertSee('Troca de discos')->assertSee('R$ 390,00');
        $pastilhas = $os->itens->firstWhere('descricao', 'Troca de pastilhas');
        $this->post('/orcamento/'.$os->token, ['nome' => 'Mariana', 'decisao' => 'aprovar', 'itens' => [$pastilhas->id]])
            ->assertRedirect('/orcamento/'.$os->token);

        $os = $this->recarregar($os);
        $this->assertSame(StatusOs::Aprovada, $os->status);
        $this->assertSame(9000, $os->total_centavos);
        $this->assertSame('Mariana (pelo link)', $os->aprovado_por);
        $this->assertSame(['aprovado', 'recusado'], $os->itens->pluck('situacao')->all());

        // equipe executa e entrega
        $this->actingAs($u);
        $this->post("/os/{$os->id}/acao/iniciar");
        $this->post("/os/{$os->id}/acao/concluir");
        $this->post("/os/{$os->id}/acao/entregar");
        $os = $this->recarregar($os);
        $this->assertSame(StatusOs::Entregue, $os->status);
        $this->assertSame(now()->addDays(90)->toDateString(), $os->garantiaAte()->toDateString());

        // depois de entregue, o link só mostra o resumo
        auth()->logout();
        $this->get('/orcamento/'.$os->token)->assertOk()->assertDontSee('Aprovar itens marcados');
    }

    public function test_cliente_recusando_tudo_volta_para_orcamento(): void
    {
        $u = $this->novaEmpresa();
        $os = $this->abrirOs($u);
        $this->item($u, $os, 'Troca de discos', '300,00');
        $this->post("/os/{$os->id}/acao/enviar");
        auth()->logout();

        $this->post('/orcamento/'.$os->token, ['nome' => 'Mariana', 'decisao' => 'recusar']);

        $os = $this->recarregar($os);
        $this->assertSame(StatusOs::Orcamento, $os->status);
        $this->assertSame(0, $os->total_centavos);
    }

    public function test_item_adicional_durante_servico_precisa_de_nova_aprovacao(): void
    {
        $u = $this->novaEmpresa();
        $os = $this->abrirOs($u);
        $this->item($u, $os, 'Troca de pastilhas', '90,00');
        $os = $this->recarregar($os);
        $this->post("/os/{$os->id}/acao/aprovar", ['itens' => [$os->itens[0]->id]]);
        $this->post("/os/{$os->id}/acao/iniciar");

        $this->item($u, $os, 'Fluido de freio', '45,00', '1', 'peca');
        $this->post("/os/{$os->id}/acao/concluir")->assertSessionHas('erro');
        $this->assertSame(StatusOs::EmServico, $this->recarregar($os)->status);

        $os = $this->recarregar($os);
        $this->post("/os/{$os->id}/acao/aprovar", ['itens' => [$os->itens[1]->id]]);
        $this->post("/os/{$os->id}/acao/concluir")->assertSessionMissing('erro');
        $os = $this->recarregar($os);
        $this->assertSame(StatusOs::Pronta, $os->status);
        $this->assertSame(13500, $os->total_centavos);
    }

    public function test_nao_pula_etapas(): void
    {
        $u = $this->novaEmpresa();
        $os = $this->abrirOs($u);

        $this->post("/os/{$os->id}/acao/enviar")->assertSessionHas('erro'); // sem itens
        $this->post("/os/{$os->id}/acao/iniciar")->assertSessionHas('erro'); // sem aprovação
        $this->post("/os/{$os->id}/acao/entregar")->assertSessionHas('erro');
        $this->post("/os/{$os->id}/acao/cancelar", ['motivo' => ''])->assertSessionHas('erro');
        $this->assertSame(StatusOs::Orcamento, $this->recarregar($os)->status);

        $this->post("/os/{$os->id}/acao/cancelar", ['motivo' => 'Cliente desistiu']);
        $this->assertSame(StatusOs::Cancelada, $this->recarregar($os)->status);
        $this->post("/os/{$os->id}/itens", ['tipo' => 'servico', 'descricao' => 'X', 'quantidade' => 1, 'valor' => '10'])->assertSessionHas('erro');

        auth()->logout();
        $this->get('/orcamento/'.$os->token)->assertNotFound();
    }

    public function test_permissoes_por_perfil(): void
    {
        $dono = $this->novaEmpresa();
        $os = $this->abrirOs($dono);
        $this->item($dono, $os, 'Troca de pastilhas', '90,00');
        $mec = $this->usuarioNaMesmaEmpresa($dono, Perfil::Mecanico);
        $caixa = $this->usuarioNaMesmaEmpresa($dono, Perfil::Caixa);
        $consultor = $this->usuarioNaMesmaEmpresa($dono, Perfil::Consultor);

        $this->actingAs($mec);
        $this->get("/os/{$os->id}")->assertOk();
        $this->get('/os/create?veiculo_id='.$os->veiculo_id)->assertForbidden();
        $this->post("/os/{$os->id}/itens", ['tipo' => 'servico', 'descricao' => 'X', 'quantidade' => 1, 'valor' => '1'])->assertForbidden();
        $this->post("/os/{$os->id}/acao/aprovar", ['itens' => [1]])->assertForbidden();
        $this->put("/os/{$os->id}/diagnostico", ['diagnostico' => 'Pastilha no fim', 'mecanico_id' => $mec->id])->assertRedirect();
        $this->assertSame('Pastilha no fim', $this->recarregar($os)->diagnostico);

        $this->actingAs($caixa)->get("/os/{$os->id}")->assertOk();
        $this->actingAs($caixa)->put("/os/{$os->id}/diagnostico", ['diagnostico' => 'x'])->assertForbidden();

        // consultor não cancela nem remove item já aprovado
        $os = $this->recarregar($os);
        $this->actingAs($dono)->post("/os/{$os->id}/acao/aprovar", ['itens' => [$os->itens[0]->id]]);
        $this->actingAs($consultor)->post("/os/{$os->id}/acao/cancelar", ['motivo' => 'x'])->assertForbidden();
        $this->actingAs($consultor)->delete("/os/{$os->id}/itens/{$os->itens[0]->id}")->assertSessionHas('erro');
        $this->assertCount(1, $this->recarregar($os)->itens);
    }

    public function test_os_de_outra_empresa_nao_aparece_e_nao_aceita_itens(): void
    {
        $a = $this->novaEmpresa('A');
        $b = $this->novaEmpresa('B');
        $osB = $this->abrirOs($b);

        $this->actingAs($a);
        $this->get("/os/{$osB->id}")->assertNotFound();
        $this->get("/os/{$osB->id}/imprimir")->assertNotFound();
        $this->post("/os/{$osB->id}/itens", ['tipo' => 'servico', 'descricao' => 'X', 'quantidade' => 1, 'valor' => '1'])->assertNotFound();
        $this->post("/os/{$osB->id}/acao/cancelar", ['motivo' => 'x'])->assertNotFound();
        $placaB = Tenant::com($b->empresa_id, fn () => $osB->veiculo->placaFormatada());
        $this->get('/os?visao=todas')->assertDontSee($placaB);

        // não abre OS para veículo de outra empresa
        $this->get('/os/create?veiculo_id='.$osB->veiculo_id)->assertNotFound();
        $this->post('/os', ['veiculo_id' => $osB->veiculo_id])->assertSessionHasErrors('veiculo_id');
    }

    public function test_link_do_orcamento_mostra_so_a_os_certa_e_token_invalido_da_404(): void
    {
        $a = $this->novaEmpresa('Oficina A');
        $b = $this->novaEmpresa('Oficina B');
        $osA = $this->abrirOs($a);
        $this->item($a, $osA, 'Serviço da A', '10,00');
        $osB = $this->abrirOs($b);
        $this->item($b, $osB, 'Serviço da B', '20,00');
        auth()->logout();
        $this->flushSession();

        $this->get('/orcamento/'.$osA->token)->assertOk()->assertSee('Oficina A')->assertSee('Serviço da A')->assertDontSee('Serviço da B');
        $this->get('/orcamento/'.str_repeat('x', 40))->assertNotFound();
        $this->get('/orcamento/curto')->assertNotFound();
        $this->assertFalse(Tenant::definido());
    }

    public function test_fotos_sao_reduzidas_e_so_abrem_para_a_mesma_empresa_ou_pelo_link(): void
    {
        Storage::fake('local');
        $a = $this->novaEmpresa('A');
        $b = $this->novaEmpresa('B');
        $os = $this->abrirOs($a);

        $this->actingAs($a)->post("/os/{$os->id}/fotos", [
            'fotos' => [UploadedFile::fake()->image('freio.jpg', 4000, 3000)],
            'legenda' => 'pastilha gasta',
        ])->assertSessionHasNoErrors();

        $foto = Tenant::com($a->empresa_id, fn () => $os->fotos()->first());
        Storage::disk('local')->assertExists($foto->caminho);
        [$l, $h] = getimagesizefromstring(Storage::disk('local')->get($foto->caminho));
        $this->assertSame([1600, 1200], [$l, $h]);

        $this->get("/os/{$os->id}/fotos/{$foto->id}")->assertOk();
        $this->actingAs($b)->get("/os/{$os->id}/fotos/{$foto->id}")->assertNotFound();
        auth()->logout();
        $this->get("/os/{$os->id}/fotos/{$foto->id}")->assertRedirect('/entrar');
        $this->get('/orcamento/'.$os->token.'/foto/'.$foto->id)->assertOk();
    }

    public function test_telas_de_os_abrem(): void
    {
        $u = $this->novaEmpresa();
        $os = $this->abrirOs($u);
        $this->item($u, $os, 'Troca de pastilhas', '90,00');

        foreach (['/os', '/os?visao=todas', "/os/{$os->id}", "/os/{$os->id}/edit", "/os/{$os->id}/imprimir", '/servicos', '/os/create?veiculo_id='.$os->veiculo_id, "/veiculos/{$os->veiculo_id}", "/clientes/{$os->cliente_id}", '/'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->getJson('/recepcao/placa?placa=ABC1D23')->assertJsonPath('html', fn ($h) => str_contains($h, 'Continuar OS 00001'));
    }

    public function test_catalogo_de_servicos(): void
    {
        $u = $this->novaEmpresa();
        $this->actingAs($u)->post('/servicos', ['nome' => 'Alinhamento', 'preco' => '120,00', 'tempo_minutos' => 60])->assertSessionHasNoErrors();
        $this->post('/servicos', ['nome' => 'Alinhamento', 'preco' => '1'])->assertSessionHasErrors('nome');

        $s = Tenant::com($u->empresa_id, fn () => Servico::first());
        $this->assertSame(12000, $s->preco_centavos);

        $mec = $this->usuarioNaMesmaEmpresa($u, Perfil::Mecanico);
        $this->actingAs($mec)->get('/servicos')->assertOk();
        $this->actingAs($mec)->post('/servicos', ['nome' => 'X', 'preco' => '1'])->assertForbidden();
    }
}
