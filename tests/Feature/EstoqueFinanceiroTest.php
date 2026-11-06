<?php

namespace Tests\Feature;

use App\Models\Caixa;
use App\Models\Cliente;
use App\Models\Comissao;
use App\Models\Compra;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\Fornecedor;
use App\Models\Lembrete;
use App\Models\OrdemServico;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\User;
use App\Models\Veiculo;
use App\Support\Perfil;
use App\Support\StatusOs;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EstoqueFinanceiroTest extends TestCase
{
    use RefreshDatabase;

    private function xml(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('nota.xml', file_get_contents(base_path('tests/Fixtures/nfe-compra.xml')));
    }

    private function em(User $u, callable $fn): mixed
    {
        return Tenant::com($u->empresa_id, $fn);
    }

    /** OS pronta com 1 serviço (R$ 100) e 2 unidades de uma peça do estoque (R$ 50 cada). */
    private function osPronta(User $u): OrdemServico
    {
        [$v, $p, $s] = $this->em($u, function () {
            $c = Cliente::create(['nome' => 'Ana Lima', 'cpf_cnpj' => '52998224725', 'whatsapp' => '(19) 99999-1111']);
            $v = Veiculo::create(['placa' => 'ABC1D23', 'modelo' => 'Onix', 'cliente_id' => $c->id, 'km_atual' => 30000]);
            $p = Produto::create(['nome' => 'Filtro de óleo', 'ncm' => '84212300', 'preco_centavos' => 5000, 'custo_centavos' => 2000, 'estoque_minimo' => 1]);
            $p->movimentar(5, 'entrada', 'manual');
            $s = Servico::create(['nome' => 'Troca de óleo', 'preco_centavos' => 10000, 'revisao_meses' => 6, 'revisao_km' => 10000]);

            return [$v, $p, $s];
        });
        $mec = $this->usuarioNaMesmaEmpresa($u, Perfil::Mecanico);
        $mec->update(['comissao_percentual' => 10]);

        $this->actingAs($u)->post('/os', ['veiculo_id' => $v->id, 'cliente_id' => $v->cliente_id, 'km_entrada' => '31000', 'mecanico_id' => $mec->id]);
        $os = $this->em($u, fn () => OrdemServico::latest('id')->first());
        $this->post("/os/{$os->id}/itens", ['tipo' => 'servico', 'servico_id' => $s->id, 'quantidade' => 1, 'valor' => '']);
        $this->post("/os/{$os->id}/itens", ['tipo' => 'peca', 'produto_id' => $p->id, 'quantidade' => 2, 'valor' => '']);
        $os = $this->em($u, fn () => OrdemServico::with('itens')->find($os->id));
        $this->post("/os/{$os->id}/acao/aprovar", ['itens' => $os->itens->pluck('id')->all()]);
        $this->post("/os/{$os->id}/acao/iniciar");
        $this->post("/os/{$os->id}/acao/concluir");

        return $this->em($u, fn () => OrdemServico::with('itens')->find($os->id));
    }

    // ---------- Estoque e compras ----------

    public function test_importa_xml_reconhece_e_lanca_estoque_e_contas_a_pagar(): void
    {
        Storage::fake('local');
        $u = $this->novaEmpresa();
        $existente = $this->em($u, fn () => Produto::create(['nome' => 'Pastilha HB20', 'codigo_barras' => '7891234567895', 'preco_centavos' => 18900]));

        $this->actingAs($u)->post('/compras', ['xml' => $this->xml()])->assertRedirect();
        $compra = $this->em($u, fn () => Compra::with('itens', 'fornecedor')->first());

        $this->assertSame('4521', $compra->numero);
        $this->assertSame('rascunho', $compra->status);
        $this->assertSame(102800, $compra->total_centavos);
        $this->assertSame('11444777000161', $compra->fornecedor->cpf_cnpj);
        $this->assertSame($existente->id, $compra->itens[0]->produto_id, 'reconhece pelo código de barras');
        $this->assertNull($compra->itens[1]->produto_id);
        $this->assertNull($compra->itens[1]->codigo_barras, 'SEM GTIN não é código de barras');
        Storage::disk('local')->assertExists($compra->xml_caminho);

        $this->post("/compras/{$compra->id}/lancar", [
            'produto' => [$compra->itens[0]->id => $existente->id, $compra->itens[1]->id => 'novo'],
            'preco' => [$compra->itens[1]->id => '45,00'],
        ])->assertSessionHasNoErrors();

        $this->em($u, function () use ($existente, $compra) {
            $this->assertSame(4.0, (float) $existente->fresh()->estoque);
            $this->assertSame(9800, $existente->fresh()->custo_centavos);
            $oleo = Produto::where('nome', 'OLEO MOTOR 5W30 SINT 1L')->first();
            $this->assertSame(24.0, (float) $oleo->estoque);
            $this->assertSame(4500, $oleo->preco_centavos);
            $this->assertSame('27101932', $oleo->ncm);
            $this->assertSame([51400, 51400], ContaPagar::orderBy('vencimento')->pluck('valor_centavos')->all());
            $this->assertSame('lancada', $compra->fresh()->status);
        });

        // mesma nota de novo: recusada
        $this->post('/compras', ['xml' => $this->xml()])->assertSessionHas('erro');
        // não lança duas vezes
        $this->post("/compras/{$compra->id}/lancar")->assertSessionHas('erro');
    }

    public function test_aprende_o_codigo_do_fornecedor_para_a_proxima_nota(): void
    {
        Storage::fake('local');
        $u = $this->novaEmpresa();
        $this->actingAs($u)->post('/compras', ['xml' => $this->xml()]);
        $compra = $this->em($u, fn () => Compra::with('itens')->first());
        $this->post("/compras/{$compra->id}/lancar");

        // segunda nota do mesmo fornecedor, outra chave
        $xml2 = str_replace(['45211000045210', '<nNF>4521'], ['45221000045229', '<nNF>4522'], file_get_contents(base_path('tests/Fixtures/nfe-compra.xml')));
        $this->post('/compras', ['xml' => UploadedFile::fake()->createWithContent('n2.xml', $xml2)]);
        $nova = $this->em($u, fn () => Compra::with('itens')->latest('id')->first());

        $this->assertNotNull($nova->itens[1]->produto_id, 'o óleo agora é reconhecido pelo código do fornecedor');
        $this->assertSame($this->em($u, fn () => $compra->fresh()->itens[1]->produto_id), $nova->itens[1]->produto_id);
    }

    public function test_xml_invalido_mostra_erro_amigavel(): void
    {
        $u = $this->novaEmpresa();
        $this->actingAs($u)->post('/compras', ['xml' => UploadedFile::fake()->createWithContent('x.xml', '<html>não é nota</html>')])
            ->assertSessionHas('erro', 'Este XML não é de uma NF-e.');
    }

    public function test_contagem_de_inventario_ajusta_o_saldo(): void
    {
        $u = $this->novaEmpresa();
        $p = $this->em($u, fn () => Produto::create(['nome' => 'Fluido', 'estoque_minimo' => 3]));
        $this->actingAs($u)->post("/produtos/{$p->id}/movimentar", ['tipo' => 'entrada', 'quantidade' => '10']);
        $this->post("/produtos/{$p->id}/movimentar", ['tipo' => 'contagem', 'quantidade' => '7']);

        $this->em($u, function () use ($p) {
            $p = $p->fresh();
            $this->assertSame(7.0, (float) $p->estoque);
            $this->assertSame([-3.0, 10.0], $p->movimentos->pluck('quantidade')->map(fn ($q) => (float) $q)->all());
            $this->assertFalse($p->noMinimo());
            $this->assertSame(0, Produto::abaixoDoMinimo()->count());
        });
        $this->post("/produtos/{$p->id}/movimentar", ['tipo' => 'saida', 'quantidade' => '4']);
        $this->assertSame(1, $this->em($u, fn () => Produto::abaixoDoMinimo()->count()));
    }

    // ---------- Pagamento, entrega, comissão, lembrete ----------

    public function test_entrega_baixa_estoque_gera_comissao_e_lembrete(): void
    {
        $u = $this->novaEmpresa();
        $os = $this->osPronta($u);
        $this->assertSame(20000, $os->total_centavos);

        $this->post("/os/{$os->id}/receber", ['pagamentos' => [['forma' => 'pix', 'valor' => '200,00']], 'entregar' => 1])->assertSessionHasNoErrors();

        $this->em($u, function () use ($os) {
            $os = $os->fresh();
            $this->assertSame(StatusOs::Entregue, $os->status);
            $this->assertSame(3.0, (float) Produto::first()->estoque);
            $this->assertTrue($os->itens()->where('tipo', 'peca')->first()->estoque_baixado);

            $c = Comissao::first();
            $this->assertSame(10000, $c->base_centavos);
            $this->assertSame(1000, $c->valor_centavos);

            $l = Lembrete::first();
            $this->assertSame('Troca de óleo', $l->descricao);
            $this->assertSame(today()->addMonths(6)->toDateString(), $l->data_prevista->toDateString());
            $this->assertSame(41000, $l->km_previsto);
            $this->assertSame(['recebida'], ContaReceber::pluck('status')->all());
        });
    }

    public function test_pagamento_dividido_com_parcelas_e_exigencia_do_caixa_para_dinheiro(): void
    {
        $u = $this->novaEmpresa();
        $os = $this->osPronta($u);

        // soma errada
        $this->post("/os/{$os->id}/receber", ['pagamentos' => [['forma' => 'pix', 'valor' => '150,00']]])->assertSessionHas('erro');
        // dinheiro sem caixa aberto
        $this->post("/os/{$os->id}/receber", ['pagamentos' => [['forma' => 'dinheiro', 'valor' => '200,00']]])->assertSessionHas('erro', 'Abra o caixa para receber em dinheiro.');

        $this->post('/caixa/abrir', ['troco' => '50,00']);
        $this->post("/os/{$os->id}/receber", ['pagamentos' => [
            ['forma' => 'dinheiro', 'valor' => '50,00'],
            ['forma' => 'prazo', 'valor' => '150,00', 'parcelas' => 3, 'vencimento' => '2026-12-10'],
        ]])->assertSessionHasNoErrors();

        $this->em($u, function () {
            $abertas = ContaReceber::where('status', 'aberta')->orderBy('vencimento')->get();
            $this->assertSame([5000, 5000, 5000], $abertas->pluck('valor_centavos')->all());
            $this->assertSame(['2026-12-10', '2027-01-10', '2027-02-10'], $abertas->map(fn ($c) => $c->vencimento->toDateString())->all());
            $this->assertSame(['1/3', '2/3', '3/3'], $abertas->pluck('parcela')->all());
            $this->assertSame(10000, Caixa::aberto()->dinheiroEsperado());
        });

        // não recebe duas vezes
        $this->post("/os/{$os->id}/receber", ['pagamentos' => [['forma' => 'pix', 'valor' => '200,00']]])->assertSessionHas('erro');

        // recebe uma parcela depois, no pix
        $parcela = $this->em($u, fn () => ContaReceber::where('status', 'aberta')->orderBy('vencimento')->first());
        $this->post("/financeiro/receber/{$parcela->id}/receber", ['forma' => 'pix'])->assertSessionHasNoErrors();
        $this->assertSame('recebida', $this->em($u, fn () => $parcela->fresh()->status));
    }

    public function test_caixa_sangria_pagamento_e_fechamento_com_diferenca(): void
    {
        $u = $this->novaEmpresa();
        $this->actingAs($u)->post('/caixa/abrir', ['troco' => '100,00']);
        $this->post('/caixa/movimento', ['tipo' => 'sangria', 'valor' => '500,00', 'descricao' => 'banco'])->assertSessionHas('erro');
        $this->post('/caixa/movimento', ['tipo' => 'suprimento', 'valor' => '50,00', 'descricao' => 'troco']);
        $this->post('/caixa/movimento', ['tipo' => 'sangria', 'valor' => '30,00', 'descricao' => 'café']);

        $conta = $this->em($u, fn () => ContaPagar::create(['categoria' => 'Outros', 'descricao' => 'Gás', 'valor_centavos' => 2000, 'vencimento' => today()]));
        $this->post("/financeiro/pagar/{$conta->id}/pagar", ['forma' => 'dinheiro'])->assertSessionHasNoErrors();
        $this->assertSame(10000, $this->em($u, fn () => Caixa::aberto()->dinheiroEsperado())); // 100 + 50 - 30 - 20

        $this->post('/caixa/fechar', ['contado' => '95,00'])->assertSessionHas('erro', 'Caixa fechado com diferença de R$ 5,00 a menos.');
        $this->em($u, function () {
            $this->assertNull(Caixa::aberto());
            $c = Caixa::first();
            $this->assertSame([10000, 9500], [$c->dinheiro_esperado_centavos, $c->dinheiro_contado_centavos]);
        });
    }

    public function test_conta_fixa_repetida_por_meses(): void
    {
        $u = $this->novaEmpresa();
        $this->actingAs($u)->post('/financeiro/pagar', ['descricao' => 'Aluguel', 'categoria' => 'Aluguel', 'valor' => '3.500,00', 'vencimento' => '2026-11-10', 'repetir' => 3])->assertSessionHasNoErrors();
        $this->assertSame(['Aluguel (1/3)', 'Aluguel (2/3)', 'Aluguel (3/3)'], $this->em($u, fn () => ContaPagar::orderBy('vencimento')->pluck('descricao')->all()));
    }

    // ---------- Permissões e isolamento ----------

    public function test_permissoes_dos_novos_modulos(): void
    {
        $dono = $this->novaEmpresa();
        $mec = $this->usuarioNaMesmaEmpresa($dono, Perfil::Mecanico);
        $estoq = $this->usuarioNaMesmaEmpresa($dono, Perfil::Estoquista);
        $caixa = $this->usuarioNaMesmaEmpresa($dono, Perfil::Caixa);

        $this->actingAs($mec);
        $this->get('/produtos')->assertOk();
        $this->get('/caixa')->assertForbidden();
        $this->get('/financeiro/receber')->assertForbidden();
        $this->get('/notas')->assertForbidden();
        $this->post('/compras', [])->assertForbidden();

        $this->actingAs($estoq);
        $this->get('/compras')->assertOk();
        $this->get('/caixa')->assertForbidden();

        $this->actingAs($caixa);
        $this->get('/caixa')->assertOk();
        $this->get('/financeiro/pagar')->assertOk();
        $this->get('/financeiro/comissoes')->assertForbidden();
        $this->get('/produtos/create')->assertForbidden();
    }

    public function test_dados_de_outra_empresa_nao_aparecem(): void
    {
        Storage::fake('local');
        $a = $this->novaEmpresa('A');
        $b = $this->novaEmpresa('B');
        $this->actingAs($b)->post('/compras', ['xml' => $this->xml()]);
        $compraB = $this->em($b, fn () => Compra::first());
        $prodB = $this->em($b, fn () => Produto::create(['nome' => 'Peça da B']));
        $contaB = $this->em($b, fn () => ContaPagar::create(['categoria' => 'Outros', 'descricao' => 'Da B', 'valor_centavos' => 100, 'vencimento' => today()]));

        $this->actingAs($a);
        $this->get("/compras/{$compraB->id}")->assertNotFound();
        $this->get("/produtos/{$prodB->id}")->assertNotFound();
        $this->post("/financeiro/pagar/{$contaB->id}/pagar", ['forma' => 'pix'])->assertNotFound();
        $this->get('/produtos')->assertDontSee('Peça da B');
        $this->get('/financeiro/pagar')->assertDontSee('Da B');

        // A pode importar a mesma nota (é outra empresa)
        $this->post('/compras', ['xml' => $this->xml()])->assertSessionMissing('erro');
        $this->assertSame(1, $this->em($a, fn () => Fornecedor::count()));
    }

    public function test_telas_abrem(): void
    {
        $u = $this->novaEmpresa();
        $os = $this->osPronta($u);
        $p = $this->em($u, fn () => Produto::first());
        foreach (['/', "/os/{$os->id}", '/produtos', '/produtos/create', "/produtos/{$p->id}", "/produtos/{$p->id}/edit", '/fornecedores', '/fornecedores/create', '/compras',
            '/caixa', '/financeiro/receber', '/financeiro/receber?filtro=vencidas', '/financeiro/pagar', '/financeiro/comissoes', '/financeiro/resumo', '/notas', '/agenda', '/lembretes', '/empresa', '/servicos'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get("/os/{$os->id}")->assertSee('Receber '.$os->totalFormatado());
    }
}
