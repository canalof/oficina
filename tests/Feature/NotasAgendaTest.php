<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Lembrete;
use App\Models\NotaFiscal;
use App\Models\OrdemServico;
use App\Models\Produto;
use App\Models\User;
use App\Models\Veiculo;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotasAgendaTest extends TestCase
{
    use RefreshDatabase;

    private function em(User $u, callable $fn): mixed
    {
        return Tenant::com($u->empresa_id, $fn);
    }

    /** OS paga com R$ 100 de serviço e R$ 50 de peça (com ou sem NCM). */
    private function osPaga(User $u, ?string $ncm = '84212300'): OrdemServico
    {
        $v = $this->em($u, function () {
            $c = Cliente::create(['nome' => 'Ana Lima', 'cpf_cnpj' => '52998224725', 'whatsapp' => '(19) 99999-1111', 'email' => 'ana@ex.com']);

            return Veiculo::create(['placa' => 'ABC1D23', 'modelo' => 'Onix', 'marca' => 'Chevrolet', 'cliente_id' => $c->id]);
        });
        $p = $this->em($u, fn () => Produto::create(['nome' => 'Filtro de óleo', 'ncm' => $ncm, 'preco_centavos' => 5000]));
        $this->actingAs($u)->post('/os', ['veiculo_id' => $v->id, 'cliente_id' => $v->cliente_id]);
        $os = $this->em($u, fn () => OrdemServico::latest('id')->first());
        $this->post("/os/{$os->id}/itens", ['tipo' => 'servico', 'descricao' => 'Troca de óleo', 'quantidade' => 1, 'valor' => '100,00']);
        $this->post("/os/{$os->id}/itens", ['tipo' => 'peca', 'produto_id' => $p->id, 'quantidade' => 1, 'valor' => '']);
        $os = $this->em($u, fn () => OrdemServico::with('itens')->find($os->id));
        $this->post("/os/{$os->id}/acao/aprovar", ['itens' => $os->itens->pluck('id')->all()]);
        $this->post("/os/{$os->id}/acao/concluir");
        $this->post("/os/{$os->id}/receber", ['pagamentos' => [['forma' => 'pix', 'valor' => '150,00']]])->assertSessionHasNoErrors();

        return $os;
    }

    private function configurarFocus(User $u): void
    {
        Empresa::whereKey($u->empresa_id)->first()->update([
            'cnpj' => '11222333000181', 'inscricao_municipal' => '12345', 'inscricao_estadual' => '123456789',
            'codigo_municipio' => '3552403', 'aliquota_iss' => 2, 'fiscal_token' => 'token-teste', 'fiscal_ambiente' => 'homologacao',
        ]);
    }

    // ---------- Notas fiscais ----------

    public function test_sem_token_emite_em_modo_demonstracao(): void
    {
        Http::preventStrayRequests();
        $u = $this->novaEmpresa();
        $os = $this->osPaga($u);

        $this->post("/os/{$os->id}/notas/nfse")->assertSessionHas('ok');
        $nota = $this->em($u, fn () => NotaFiscal::first());
        $this->assertSame(['nfse', 'autorizada', 'demonstracao'], [$nota->tipo, $nota->status, $nota->provedor]);
        $this->assertSame(10000, $nota->valor_centavos);

        // não duplica
        $this->post("/os/{$os->id}/notas/nfse")->assertSessionHas('erro');
        $this->get("/os/{$os->id}")->assertSee('Demonstração');
    }

    public function test_nao_emite_antes_do_pagamento_nem_peca_sem_ncm(): void
    {
        $u = $this->novaEmpresa();
        $os = $this->osPaga($u, ncm: null);
        $this->post("/os/{$os->id}/notas/nfce")->assertSessionHas('erro', 'A peça "Filtro de óleo" está sem NCM. Use uma peça do estoque com NCM cadastrado.');

        $this->actingAs($u)->post('/os', ['veiculo_id' => $os->veiculo_id]);
        $nova = $this->em($u, fn () => OrdemServico::latest('id')->first());
        $this->post("/os/{$nova->id}/notas/nfse")->assertSessionHas('erro');
    }

    public function test_nfse_pela_focus_envia_dados_certos_e_atualiza_situacao(): void
    {
        $u = $this->novaEmpresa();
        $this->configurarFocus($u);
        $os = $this->osPaga($u);

        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfse?ref=*' => Http::response(['status' => 'processando_autorizacao'], 202),
            'homologacao.focusnfe.com.br/v2/nfse/*' => Http::response(['status' => 'autorizado', 'numero' => '321', 'codigo_verificacao' => 'AB12', 'url' => 'https://prefeitura.exemplo/nota/321', 'caminho_xml_nota_fiscal' => '/arquivos/nfse.xml']),
        ]);

        $this->post("/os/{$os->id}/notas/nfse")->assertSessionHas('ok');
        Http::assertSent(function (Request $r) {
            if (! str_contains($r->url(), '/v2/nfse?ref=')) {
                return false;
            }
            $d = $r->data();

            return $r->method() === 'POST'
                && $r->hasHeader('Authorization', 'Basic '.base64_encode('token-teste:'))
                && $d['prestador'] === ['cnpj' => '11222333000181', 'inscricao_municipal' => '12345', 'codigo_municipio' => '3552403']
                && $d['servico']['valor_servicos'] == 100.0
                && $d['servico']['item_lista_servico'] === '14.01'
                && $d['servico']['valor_iss'] == 2.0
                && str_contains($d['servico']['discriminacao'], 'Troca de óleo')
                && $d['tomador']['cpf'] === '52998224725';
        });

        $nota = $this->em($u, fn () => NotaFiscal::first());
        $this->assertSame('processando', $nota->status);

        $this->post("/notas/{$nota->id}/atualizar");
        $nota = $this->em($u, fn () => $nota->fresh());
        $this->assertSame(['autorizada', '321', 'https://prefeitura.exemplo/nota/321', 'https://homologacao.focusnfe.com.br/arquivos/nfse.xml'], [$nota->status, $nota->numero, $nota->url_pdf, $nota->url_xml]);
    }

    public function test_nfce_envia_itens_e_forma_de_pagamento_e_erro_vira_mensagem(): void
    {
        $u = $this->novaEmpresa();
        $this->configurarFocus($u);
        $os = $this->osPaga($u);
        Http::fake(['*' => Http::sequence()
            ->push(['status' => 'erro_autorizacao', 'mensagem_sefaz' => 'Rejeição 778: NCM inexistente'], 422)
            ->push(['status' => 'autorizado', 'numero' => '9', 'chave_nfe' => 'NFe3526', 'caminho_danfe' => '/danfe.pdf'])]);

        $this->post("/os/{$os->id}/notas/nfce")->assertSessionHas('erro', 'O emissor recusou a nota: Rejeição 778: NCM inexistente');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/v2/nfce?ref=')
            && $r['cnpj_emitente'] === '11222333000181'
            && $r['items'][0]['codigo_ncm'] === '84212300'
            && $r['items'][0]['valor_bruto'] == 50.0
            && $r['items'][0]['icms_situacao_tributaria'] === '102'
            && $r['formas_pagamento'] === [['forma_pagamento' => '17', 'valor_pagamento' => 50.0]]
            && $r['cpf_destinatario'] === '52998224725');

        // nota com erro pode ser emitida de novo depois de corrigir
        $this->post("/os/{$os->id}/notas/nfce")->assertSessionHas('ok');
        $this->assertSame(['erro', 'autorizada'], $this->em($u, fn () => NotaFiscal::orderBy('id')->pluck('status')->all()));
    }

    public function test_cancelamento_exige_justificativa(): void
    {
        $u = $this->novaEmpresa();
        $os = $this->osPaga($u);
        $this->post("/os/{$os->id}/notas/nfse");
        $nota = $this->em($u, fn () => NotaFiscal::first());

        $this->post("/notas/{$nota->id}/cancelar", ['justificativa' => 'curta'])->assertSessionHas('erro');
        $this->post("/notas/{$nota->id}/cancelar", ['justificativa' => 'Valor lançado errado na OS'])->assertSessionHas('ok');
        $this->assertSame('cancelada', $this->em($u, fn () => $nota->fresh()->status));
    }

    public function test_token_fiscal_fica_criptografado_e_nao_aparece_na_tela(): void
    {
        $u = $this->novaEmpresa();
        $this->configurarFocus($u);
        $bruto = \DB::table('empresas')->where('id', $u->empresa_id)->value('fiscal_token');
        $this->assertNotSame('token-teste', $bruto);
        $this->actingAs($u)->get('/empresa')->assertOk()->assertDontSee('token-teste')->assertSee('Token salvo');
    }

    // ---------- Agenda e lembretes ----------

    public function test_agendamento_liga_placa_ao_veiculo_e_muda_status(): void
    {
        $u = $this->novaEmpresa();
        $v = $this->em($u, function () {
            $c = Cliente::create(['nome' => 'Ana Lima', 'whatsapp' => '(19) 99999-1111']);

            return Veiculo::create(['placa' => 'ABC1234', 'modelo' => 'Gol', 'cliente_id' => $c->id]);
        });

        $this->actingAs($u)->post('/agenda', [
            'cliente_id' => $v->cliente_id, 'placa' => 'abc1c34', 'inicio' => '2026-11-10 09:00', 'duracao_minutos' => 60, 'servico' => 'Revisão',
        ])->assertSessionHasNoErrors();

        $a = $this->em($u, fn () => Agendamento::first());
        $this->assertSame($v->id, $a->veiculo_id, 'placa Mercosul equivalente encontra o carro');
        $this->assertSame('(19) 99999-1111', $a->telefone);

        $this->put("/agenda/{$a->id}", ['status' => 'confirmado']);
        $this->assertSame('confirmado', $this->em($u, fn () => $a->fresh()->status));
        $this->get('/agenda?semana=2026-11-10')->assertSee('Ana Lima')->assertSee('Chegou: abrir OS', false);

        $outra = $this->novaEmpresa('Outra');
        $this->actingAs($outra)->put("/agenda/{$a->id}", ['status' => 'faltou'])->assertNotFound();
    }

    public function test_lembrete_sem_whatsapp_automatico_abre_o_whatsapp(): void
    {
        $u = $this->novaEmpresa();
        $l = $this->lembrete($u);

        $r = $this->actingAs($u)->post("/lembretes/{$l->id}/enviar");
        $this->assertStringStartsWith('https://wa.me/5519999991111?text=', $r->headers->get('Location'));
        $this->assertStringContainsString(rawurlencode('Troca de óleo'), $r->headers->get('Location'));
        $this->assertSame('enviado', $this->em($u, fn () => $l->fresh()->status));
    }

    public function test_comando_envia_lembretes_pela_evolution_api(): void
    {
        config(['whatsapp.url' => 'https://evo.exemplo.com', 'whatsapp.chave' => 'chave-evo']);
        Http::fake(['evo.exemplo.com/*' => Http::response(['key' => ['id' => 'x']], 201)]);
        $u = $this->novaEmpresa();
        Empresa::whereKey($u->empresa_id)->update(['whatsapp_instancia' => 'oficina1']);
        $l = $this->lembrete($u);
        $longe = $this->lembrete($u, today()->addDays(40));

        $this->artisan('torque:lembretes')->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://evo.exemplo.com/message/sendText/oficina1'
            && $r->hasHeader('apikey', 'chave-evo') && $r['number'] === '5519999991111' && str_contains($r['text'], 'Troca de óleo'));
        Http::assertSentCount(1);
        $this->assertSame(['enviado', 'pendente'], $this->em($u, fn () => [$l->fresh()->status, $longe->fresh()->status]));
    }

    private function lembrete(User $u, $data = null): Lembrete
    {
        return $this->em($u, function () use ($data) {
            $c = Cliente::firstOrCreate(['nome' => 'Ana Lima'], ['whatsapp' => '(19) 99999-1111']);
            $v = Veiculo::firstOrCreate(['placa' => 'ABC1234'], ['modelo' => 'Gol', 'cliente_id' => $c->id]);

            return Lembrete::create(['veiculo_id' => $v->id, 'cliente_id' => $c->id, 'descricao' => 'Troca de óleo', 'data_prevista' => $data ?? today()->addDays(3)]);
        });
    }
}
