<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Fabricante;
use App\Models\OrdemServico;
use App\Models\Produto;
use App\Models\User;
use App\Models\Veiculo;
use App\Support\Perfil;
use App\Support\Renavam;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CadastrosEstoqueTest extends TestCase
{
    use RefreshDatabase;

    private function em(User $u, callable $fn): mixed
    {
        return Tenant::com($u->empresa_id, $fn);
    }

    private function abrirOs(User $u): OrdemServico
    {
        $v = $this->em($u, fn () => Veiculo::create(['placa' => 'ABC1D23', 'modelo' => 'HB20']));
        $this->actingAs($u)->post('/os', ['veiculo_id' => $v->id])->assertRedirect();

        return $this->em($u, fn () => OrdemServico::latest('id')->first());
    }

    private function csv(string $conteudo): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('planilha.csv', $conteudo);
    }

    // ---------- Veículo ----------

    public function test_renavam_confere_digito(): void
    {
        $this->assertTrue(Renavam::valido('00639884962'));
        $this->assertTrue(Renavam::valido('639884962'));
        $this->assertFalse(Renavam::valido('00639884961'));
        $this->assertFalse(Renavam::valido('11111111111'));
        $this->assertSame('00639884962', Renavam::normalizar('639.884.962'));
    }

    public function test_veiculo_guarda_motor_e_recusa_renavam_invalido(): void
    {
        $u = $this->novaEmpresa();
        $v = $this->em($u, fn () => Veiculo::create(['placa' => 'ABC1D23', 'modelo' => 'Onix']));
        $this->actingAs($u)->put("/veiculos/{$v->id}", ['placa' => 'ABC1D23', 'modelo' => 'Onix', 'renavam' => '12345678901'])
            ->assertSessionHasErrors('renavam');
        $this->put("/veiculos/{$v->id}", ['placa' => 'ABC1D23', 'modelo' => 'Onix', 'motor' => '1.0 Turbo', 'renavam' => '639884962'])
            ->assertSessionHasNoErrors();
        $v = $this->em($u, fn () => $v->fresh());
        $this->assertSame('00639884962', $v->renavam);
        $this->assertSame('1.0 Turbo', $v->motor);
    }

    // ---------- OS: corrigir item ----------

    public function test_corrigir_valor_de_item_aprovado_volta_para_aprovacao(): void
    {
        $u = $this->novaEmpresa();
        $os = $this->abrirOs($u);
        $this->actingAs($u)->post("/os/{$os->id}/itens", ['tipo' => 'servico', 'descricao' => 'Alinhamento', 'quantidade' => '1', 'valor' => '80,00']);
        $os = $this->em($u, fn () => OrdemServico::with('itens')->find($os->id));
        $item = $os->itens->first();
        $this->post("/os/{$os->id}/acao/aprovar", ['itens' => [$item->id]]);
        $versao = $this->em($u, fn () => $os->fresh()->versao);

        // só a descrição: continua aprovado
        $this->put("/os/{$os->id}/itens/{$item->id}", ['descricao' => 'Alinhamento 3D', 'quantidade' => '1', 'valor' => '80,00'])->assertSessionHasNoErrors();
        $this->assertSame('aprovado', $this->em($u, fn () => $item->fresh()->situacao));

        // valor muda: volta para pendente, total e versão mudam
        $this->put("/os/{$os->id}/itens/{$item->id}", ['descricao' => 'Alinhamento 3D', 'quantidade' => '2', 'valor' => '90,00'])->assertSessionHasNoErrors();
        $this->em($u, function () use ($item, $os, $versao) {
            $i = $item->fresh();
            $this->assertSame('pendente', $i->situacao);
            $this->assertSame(9000, $i->valor_unitario_centavos);
            $o = $os->fresh();
            $this->assertSame(18000, $o->total_centavos);
            $this->assertGreaterThan($versao, $o->versao);
        });
    }

    public function test_nao_corrige_item_de_outra_empresa(): void
    {
        $a = $this->novaEmpresa('A');
        $b = $this->novaEmpresa('B');
        $os = $this->abrirOs($a);
        $this->actingAs($a)->post("/os/{$os->id}/itens", ['tipo' => 'servico', 'descricao' => 'X', 'quantidade' => '1', 'valor' => '10']);
        $item = $this->em($a, fn () => $os->itens()->first());
        $this->actingAs($b)->put("/os/{$os->id}/itens/{$item->id}", ['descricao' => 'Y', 'quantidade' => '1', 'valor' => '1'])->assertNotFound();
    }

    // ---------- Histórico ----------

    public function test_historico_mostra_antes_e_depois_e_respeita_permissao_e_empresa(): void
    {
        $u = $this->novaEmpresa();
        $c = $this->em($u, fn () => Cliente::create(['nome' => 'João Lima', 'telefone' => '1933334444']));
        $this->actingAs($u)->put("/clientes/{$c->id}", ['nome' => 'João Pedro Lima', 'tipo_pessoa' => 'PF', 'telefone' => '1933334444'])->assertSessionHasNoErrors();

        $this->get("/historico/cliente/{$c->id}")->assertOk()->assertSee('João Lima')->assertSee('João Pedro Lima')->assertSee('Alterado');

        $mec = $this->usuarioNaMesmaEmpresa($u, Perfil::Mecanico);
        $this->actingAs($mec)->get("/historico/cliente/{$c->id}")->assertForbidden();

        $outro = $this->novaEmpresa('Outra');
        $this->actingAs($outro)->get("/historico/cliente/{$c->id}")->assertNotFound();
    }

    // ---------- Categorias e fabricantes ----------

    public function test_categorias_e_fabricantes_por_empresa(): void
    {
        $a = $this->novaEmpresa('A');
        $b = $this->novaEmpresa('B');
        $this->actingAs($a)->post('/cadastros/categorias', ['nome' => 'Filtros'])->assertSessionHasNoErrors();
        $this->post('/cadastros/categorias', ['nome' => 'Filtros'])->assertSessionHasErrors('nome');
        $this->post('/cadastros/fabricantes', ['nome' => 'Bosch'])->assertSessionHasNoErrors();
        $this->actingAs($b)->post('/cadastros/categorias', ['nome' => 'Filtros'])->assertSessionHasNoErrors(); // outra empresa pode
        $this->get('/cadastros/fabricantes')->assertOk()->assertDontSee('Bosch');

        $cat = $this->em($a, fn () => Categoria::first());
        $this->actingAs($b)->put("/cadastros/categorias/{$cat->id}", ['nome' => 'Invadido', 'ativo' => 1])->assertNotFound();
        $this->actingAs($a)->put("/cadastros/categorias/{$cat->id}", ['nome' => 'Filtros e óleos', 'ativo' => 0])->assertSessionHasNoErrors();
        $this->assertSame('Filtros e óleos', $this->em($a, fn () => $cat->fresh()->nome));

        // produto não aceita categoria de outra empresa
        $catB = $this->em($b, fn () => Categoria::first());
        $this->actingAs($a)->post('/produtos', ['nome' => 'Filtro', 'unidade' => 'UN', 'categoria_id' => $catB->id])->assertSessionHasErrors('categoria_id');

        $mec = $this->usuarioNaMesmaEmpresa($a, Perfil::Mecanico);
        $this->actingAs($mec)->post('/cadastros/fabricantes', ['nome' => 'X'])->assertForbidden();
    }

    // ---------- Estoque ----------

    public function test_ajuste_exige_motivo_e_guarda_saldo_anterior(): void
    {
        $u = $this->novaEmpresa();
        $p = $this->em($u, fn () => Produto::create(['nome' => 'Óleo 5W30', 'unidade' => 'L']));
        $this->actingAs($u)->post("/produtos/{$p->id}/movimentar", ['tipo' => 'entrada', 'quantidade' => '10'])->assertSessionHasErrors('observacao');
        $this->post("/produtos/{$p->id}/movimentar", ['tipo' => 'entrada', 'quantidade' => '10', 'observacao' => 'Compra avulsa']);
        $this->post("/produtos/{$p->id}/movimentar", ['tipo' => 'perda', 'quantidade' => '1,5', 'observacao' => 'Vazou no galão']);
        $this->post("/produtos/{$p->id}/movimentar", ['tipo' => 'devolucao_cliente', 'quantidade' => '1', 'observacao' => 'Cliente devolveu']);
        $this->post("/produtos/{$p->id}/movimentar", ['tipo' => 'devolucao_fornecedor', 'quantidade' => '2', 'observacao' => 'Lote errado']);

        $this->em($u, function () use ($p, $u) {
            $p = $p->fresh();
            $this->assertSame(7.5, (float) $p->estoque);
            $m = $p->movimentos()->reorder("id")->get();
            $this->assertSame([0.0, 10.0, 8.5, 9.5], $m->pluck('saldo_anterior')->map(fn ($x) => (float) $x)->all());
            $this->assertSame([10.0, 8.5, 9.5, 7.5], $m->pluck('saldo')->map(fn ($x) => (float) $x)->all());
            $this->assertSame(['manual', 'perda', 'devolucao_cliente', 'devolucao_fornecedor'], $m->pluck('origem')->all());
            $this->assertSame('Vazou no galão', $m[1]->observacao);
            $this->assertSame($u->id, $m[1]->user_id);
        });
    }

    public function test_custo_medio_ponderado_e_historico_de_preco(): void
    {
        $u = $this->novaEmpresa();
        $p = $this->em($u, function () {
            $p = Produto::create(['nome' => 'Pastilha', 'custo_centavos' => 0]);
            $p->movimentar(10, 'entrada', 'manual', null, 1000); // 10 x R$ 10

            return $p;
        });
        $this->assertSame(1000, $this->em($u, fn () => $p->fresh()->custo_centavos));
        $this->actingAs($u)->post("/produtos/{$p->id}/movimentar", ['tipo' => 'entrada', 'quantidade' => '10', 'custo' => '20,00', 'observacao' => 'Compra']);
        $p = $this->em($u, fn () => $p->fresh());
        $this->assertSame(1500, $p->custo_centavos); // (10*10 + 10*20) / 20
        $this->assertSame(2000, $p->ultimo_custo_centavos);

        // saída não muda o custo
        $this->post("/produtos/{$p->id}/movimentar", ['tipo' => 'perda', 'quantidade' => '5', 'observacao' => 'Quebrou']);
        $this->assertSame(1500, $this->em($u, fn () => $p->fresh()->custo_centavos));

        $this->get("/produtos/{$p->id}")->assertOk()->assertSee('Histórico de preço e custo')->assertSee('15,00');
    }

    // ---------- Duplicados ----------

    public function test_avisa_cliente_duplicado_e_permite_confirmar(): void
    {
        $u = $this->novaEmpresa();
        $this->em($u, fn () => Cliente::create(['nome' => 'Carlos Mendes', 'whatsapp' => '(19) 98888-7777']));
        $this->actingAs($u)->post('/clientes', ['nome' => 'carlos  mendes', 'tipo_pessoa' => 'PF'])->assertSessionHas('duplicados');
        $this->post('/clientes', ['nome' => 'Outro Nome', 'tipo_pessoa' => 'PF', 'whatsapp' => '19 98888 7777'])->assertSessionHas('duplicados');
        $this->assertSame(1, $this->em($u, fn () => Cliente::count()));
        $this->post('/clientes', ['nome' => 'Carlos Mendes', 'tipo_pessoa' => 'PF', 'confirmar_duplicado' => 1])->assertSessionMissing('duplicados');
        $this->assertSame(2, $this->em($u, fn () => Cliente::count()));

        // cliente de outra empresa não conta como duplicado
        $b = $this->novaEmpresa('B');
        $this->actingAs($b)->post('/clientes', ['nome' => 'Carlos Mendes', 'tipo_pessoa' => 'PF'])->assertSessionMissing('duplicados');
    }

    // ---------- Importar e exportar ----------

    public function test_importa_clientes_atualiza_pelo_documento_e_aponta_erros(): void
    {
        $u = $this->novaEmpresa();
        $this->em($u, fn () => Cliente::create(['nome' => 'Nome antigo', 'cpf_cnpj' => '52998224725']));
        $conteudo = mb_convert_encoding("nome;cpf_cnpj;telefone;cidade\nMaria José;529.982.247-25;(19) 3333-4444;Sumaré\nJosé Souza;;(19) 2222-1111;Campinas\nSem doc válido;111.111.111-12;;\n;;;\n", 'Windows-1252', 'UTF-8');

        $this->actingAs($u)->post('/importar/clientes', ['arquivo' => $this->csv($conteudo)])
            ->assertSessionHas('resultado', fn ($r) => $r['criados'] === 1 && $r['atualizados'] === 1 && count($r['erros']) === 1 && str_contains($r['erros'][0], 'Linha 4'));

        $this->em($u, function () {
            $this->assertSame(2, Cliente::count());
            $maria = Cliente::where('cpf_cnpj', '52998224725')->first();
            $this->assertSame('Maria José', $maria->nome);
            $this->assertSame('Sumaré', $maria->cidade);
        });

        $outra = $this->novaEmpresa('Outra');
        $this->assertSame(0, $this->em($outra, fn () => Cliente::count()));
        $mec = $this->usuarioNaMesmaEmpresa($u, Perfil::Mecanico);
        $this->actingAs($mec)->post('/importar/clientes', ['arquivo' => $this->csv("nome\nX\n")])->assertForbidden();
    }

    public function test_importa_produtos_com_estoque_inicial_e_cria_fabricante(): void
    {
        $u = $this->novaEmpresa();
        $conteudo = "codigo,nome,fabricante,categoria,unidade,custo,preco,estoque_atual\nFO-1,Filtro de óleo,Tecfil,Filtros,UN,\"15,00\",\"35,00\",10\nOL-1,Óleo 5W30,Mobil,,L,\"30,00\",\"55,00\",\nX-1,Peça estranha,,,CAIXOTE,,,\n";
        $this->actingAs($u)->post('/importar/produtos', ['arquivo' => $this->csv($conteudo)])
            ->assertSessionHas('resultado', fn ($r) => $r['criados'] === 2 && count($r['erros']) === 1);

        $this->em($u, function () {
            $f = Produto::where('codigo', 'FO-1')->first();
            $this->assertSame(10.0, (float) $f->estoque);
            $this->assertSame(1500, $f->custo_centavos);
            $this->assertSame(3500, $f->preco_centavos);
            $this->assertSame('Tecfil', $f->fabricante->nome);
            $this->assertSame('Filtros', $f->categoria->nome);
            $this->assertSame(1, $f->movimentos()->count());
            $this->assertSame(2, Fabricante::count());
        });

        // reimportar atualiza preço e não mexe no estoque
        $this->post('/importar/produtos', ['arquivo' => $this->csv("codigo;nome;preco;estoque_atual\nFO-1;Filtro de óleo PSL55;39,90;99\n")])
            ->assertSessionHas('resultado', fn ($r) => $r['atualizados'] === 1);
        $this->em($u, function () {
            $f = Produto::where('codigo', 'FO-1')->first();
            $this->assertSame(3990, $f->preco_centavos);
            $this->assertSame(10.0, (float) $f->estoque);
        });
    }

    public function test_exporta_so_da_empresa_e_neutraliza_formulas(): void
    {
        $a = $this->novaEmpresa('A');
        $b = $this->novaEmpresa('B');
        $this->em($a, fn () => Cliente::create(['nome' => '=HYPERLINK("x")', 'cpf_cnpj' => '52998224725']));
        $this->em($b, fn () => Cliente::create(['nome' => 'Cliente da B']));

        $csv = $this->actingAs($a)->get('/exportar/clientes')->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('529.982.247-25', $csv);
        $this->assertStringNotContainsString('Cliente da B', $csv);

        $this->get('/exportar/produtos')->assertOk();
        $this->get('/exportar/veiculos')->assertOk();
        $this->get('/exportar/fornecedores')->assertOk();
        $this->get('/importar/clientes/modelo')->assertOk();
        $this->get('/importar/produtos')->assertOk()->assertSee('modelo da planilha');

        // mecânico vê clientes, mas não leva a base inteira
        $mec = $this->usuarioNaMesmaEmpresa($a, Perfil::Mecanico);
        $this->actingAs($mec)->get('/exportar/clientes')->assertForbidden();
        $this->get('/clientes')->assertOk()->assertDontSee('/exportar/clientes');
    }
}
