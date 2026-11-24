<?php

namespace Tests\Feature;

use App\Models\Balanco;
use App\Models\Produto;
use App\Models\User;
use App\Support\Perfil;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BalancoTest extends TestCase
{
    use RefreshDatabase;

    private function em(User $u, callable $fn): mixed
    {
        return Tenant::com($u->empresa_id, $fn);
    }

    private function produto(User $u, string $nome, float $saldo, array $extra = []): Produto
    {
        return $this->em($u, function () use ($nome, $saldo, $extra) {
            $p = Produto::create(['nome' => $nome, 'unidade' => 'UN', 'preco_centavos' => 5000, 'ativo' => true] + $extra);
            if ($saldo > 0) {
                $p->movimentar($saldo, 'entrada', 'manual', null, 1000, 'Estoque inicial');
            }

            return $p->fresh();
        });
    }

    private function saldo(User $u, Produto $p): float
    {
        return (float) $this->em($u, fn () => $p->fresh()->estoque);
    }

    public function test_aplica_so_as_diferencas_e_ignora_os_em_branco(): void
    {
        $u = $this->novaEmpresa();
        $filtro = $this->produto($u, 'Filtro de óleo', 10);
        $pastilha = $this->produto($u, 'Pastilha de freio', 4);
        $oleo = $this->produto($u, 'Óleo 5W30', 20);

        $this->actingAs($u)->get('/estoque/balanco/novo')->assertOk()->assertSee('Filtro de óleo')->assertSee('Aplicar balanço');

        $r = $this->post('/estoque/balanco', [
            'contado' => [$filtro->id => '8', $pastilha->id => '4', $oleo->id => ''],
            'visto' => [$filtro->id => '10', $pastilha->id => '4', $oleo->id => '20'],
            'observacao' => 'Fim do mês',
        ]);
        $balanco = $this->em($u, fn () => Balanco::first());
        $r->assertRedirect("/estoque/balanco/{$balanco->id}");

        $this->assertSame(8.0, $this->saldo($u, $filtro));
        $this->assertSame(4.0, $this->saldo($u, $pastilha));
        $this->assertSame(20.0, $this->saldo($u, $oleo));
        $this->assertSame(2, $balanco->produtos_contados);
        $this->assertSame(1, $balanco->produtos_ajustados);
        $this->assertSame(-2000, $balanco->diferenca_valor_centavos); // -2 un x R$ 10

        $mov = $this->em($u, fn () => $filtro->fresh()->movimentos()->first());
        $this->assertSame('balanco', $mov->origem);
        $this->assertSame($balanco->id, $mov->origem_id);
        $this->assertSame('Balanço de estoque', $mov->origemTexto());
        $this->assertSame($u->id, $mov->user_id);
        $this->assertStringContainsString("Balanço nº {$balanco->id} · Fim do mês", $mov->observacao);

        $this->get("/estoque/balanco/{$balanco->id}")->assertOk()->assertSee('Pastilha de freio')->assertSee('Bateu')->assertSee('−2');
        $this->get('/estoque/balanco')->assertOk()->assertSee('Fim do mês');
    }

    public function test_aceita_decimal_com_virgula_e_recusa_quantidade_invalida(): void
    {
        $u = $this->novaEmpresa();
        $oleo = $this->produto($u, 'Óleo a granel', 10);
        $filtro = $this->produto($u, 'Filtro', 3);

        $this->actingAs($u)->post('/estoque/balanco', ['contado' => [$oleo->id => '7,5', $filtro->id => 'abc']])
            ->assertSessionHasErrors("contado.{$filtro->id}");
        $this->assertSame(10.0, $this->saldo($u, $oleo)); // nada aplicado com erro na lista

        $this->post('/estoque/balanco', ['contado' => [$oleo->id => '-1']])->assertSessionHasErrors("contado.{$oleo->id}");
        $this->post('/estoque/balanco', ['contado' => [$oleo->id => '', $filtro->id => '']])->assertSessionHasErrors('contado');

        $this->post('/estoque/balanco', ['contado' => [$oleo->id => '7,5']])->assertRedirect();
        $this->assertSame(7.5, $this->saldo($u, $oleo));
    }

    public function test_marca_produto_que_mudou_depois_da_contagem(): void
    {
        $u = $this->novaEmpresa();
        $p = $this->produto($u, 'Vela de ignição', 10);
        // vendeu 1 depois que a tela foi aberta (visto = 10)
        $this->em($u, fn () => $p->movimentar(-1, 'saida', 'os', null, null, 'OS'));

        $this->actingAs($u)->post('/estoque/balanco', ['contado' => [$p->id => '9'], 'visto' => [$p->id => '10']]);
        $item = $this->em($u, fn () => Balanco::first()->itens()->first());
        $this->assertTrue($item->mudouDurante());
        $this->assertSame(9.0, $this->saldo($u, $p));
        $this->get('/estoque/balanco/'.$item->balanco_id)->assertSee('entre a contagem e a aplicação');
    }

    public function test_planilha_baixa_preenche_a_conferencia_e_so_aplica_ao_confirmar(): void
    {
        $u = $this->novaEmpresa();
        $p = $this->produto($u, 'Amortecedor dianteiro', 6, ['codigo' => 'AM-01', 'codigo_barras' => '7891234567895']);
        $q = $this->produto($u, 'Correia dentada', 2, ['codigo' => 'CO-02']);

        $csv = $this->actingAs($u)->get('/estoque/balanco/planilha')->assertOk()->streamedContent();
        $this->assertStringContainsString("id;codigo;codigo_barras;nome;local;unidade;estoque_sistema;contado", $csv);
        $this->assertStringContainsString('Amortecedor dianteiro', $csv);

        // Excel devolve com vírgula, uma linha por código de barras, outra em branco
        $preenchida = "id;codigo;codigo_barras;nome;contado\n;;7891234567895;Amortecedor;5\n{$q->id};CO-02;;Correia;\n";
        $this->post('/estoque/balanco/planilha', ['arquivo' => UploadedFile::fake()->createWithContent('b.csv', $preenchida)])
            ->assertRedirect('/estoque/balanco/novo');
        $this->assertSame(6.0, $this->saldo($u, $p)); // ainda não aplicou

        $this->get('/estoque/balanco/novo')->assertOk()->assertSee('Quantidades da planilha carregadas (1 produtos)', false)->assertSee('Amortecedor dianteiro')->assertDontSee('Correia dentada');
        $this->post('/estoque/balanco', ['contado' => [$p->id => '5']])->assertRedirect();
        $this->assertSame(5.0, $this->saldo($u, $p));
        $this->get('/estoque/balanco/novo')->assertDontSee('Quantidades da planilha carregadas');
    }

    public function test_planilha_com_erro_nao_carrega_nada(): void
    {
        $u = $this->novaEmpresa();
        $this->produto($u, 'Filtro', 3, ['codigo' => 'F1']);

        $this->actingAs($u)->post('/estoque/balanco/planilha', ['arquivo' => UploadedFile::fake()->createWithContent('b.csv', "codigo;contado\nF1;2\nNAOEXISTE;4\nF1;x\n")])
            ->assertSessionHas('erros_planilha', fn ($e) => count($e) === 2 && str_contains($e[0], 'Linha 3') && str_contains($e[1], 'Linha 4'));
        $this->assertNull(session('balanco_planilha'));

        $this->post('/estoque/balanco/planilha', ['arquivo' => UploadedFile::fake()->createWithContent('b.csv', "nome;quantidade\nFiltro;2\n")])
            ->assertSessionHas('erro');
    }

    public function test_quem_so_ve_estoque_nao_aplica_balanco(): void
    {
        $dono = $this->novaEmpresa();
        $p = $this->produto($dono, 'Filtro', 3);
        $mecanico = $this->usuarioNaMesmaEmpresa($dono, Perfil::Mecanico);

        $this->actingAs($mecanico)->get('/estoque/balanco')->assertOk();
        $this->get('/estoque/balanco/novo')->assertForbidden();
        $this->post('/estoque/balanco', ['contado' => [$p->id => '1']])->assertForbidden();
        $this->get('/estoque/balanco/planilha')->assertForbidden();
        $this->assertSame(3.0, $this->saldo($dono, $p));
    }

    public function test_isolamento_entre_empresas(): void
    {
        $a = $this->novaEmpresa('Oficina A');
        $b = $this->novaEmpresa('Oficina B');
        $deA = $this->produto($a, 'Produto da A', 5, ['codigo' => 'X1']);
        $deB = $this->produto($b, 'Produto da B', 5, ['codigo' => 'X1']);

        // B não consegue contar produto da A pelo id
        $this->actingAs($b)->post('/estoque/balanco', ['contado' => [$deA->id => '0']])->assertSessionHasErrors('contado');
        $this->assertSame(5.0, $this->saldo($a, $deA));

        // a tela e a planilha de B só mostram produtos de B
        $this->get('/estoque/balanco/novo')->assertSee('Produto da B')->assertDontSee('Produto da A');
        $this->assertStringNotContainsString('Produto da A', $this->get('/estoque/balanco/planilha')->streamedContent());

        // planilha com o id da A e o mesmo código: o id é ignorado e o código acha o produto de B
        $this->post('/estoque/balanco/planilha', ['arquivo' => UploadedFile::fake()->createWithContent('b.csv', "id;codigo;contado\n{$deA->id};X1;2\n")]);
        $this->assertSame([$deB->id => 2.0], session('balanco_planilha'));

        // balanço de B não abre para A
        $this->post('/estoque/balanco', ['contado' => [$deB->id => '2']]);
        $balancoB = $this->em($b, fn () => Balanco::first());
        $this->actingAs($a)->get("/estoque/balanco/{$balancoB->id}")->assertNotFound();
        $this->get('/estoque/balanco')->assertDontSee('Produto da B');
        $this->assertSame(5.0, $this->saldo($a, $deA));
        $this->assertSame(2.0, $this->saldo($b, $deB));
    }
}
