<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Caixa;
use App\Models\Cliente;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\FaturaPlataforma;
use App\Models\OrcamentoAceite;
use App\Models\OrdemServico;
use App\Models\Plano;
use App\Models\Produto;
use App\Models\User;
use App\Models\Veiculo;
use App\Notifications\RedefinirSenha;
use App\Support\Perfil;
use App\Support\StatusOs;
use App\Support\Tenant;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PlataformaSegurancaTest extends TestCase
{
    use RefreshDatabase;

    private function em(User $u, callable $fn): mixed
    {
        return Tenant::com($u->empresa_id, $fn);
    }

    private function osComItem(User $u, string $valor = '100,00', ?Produto $p = null): OrdemServico
    {
        $v = $this->em($u, fn () => Veiculo::create(['placa' => 'ABC1234', 'modelo' => 'Gol', 'cliente_id' => Cliente::create(['nome' => 'Ana', 'whatsapp' => '(19) 99999-1111'])->id]));
        $this->actingAs($u)->post('/os', ['veiculo_id' => $v->id, 'cliente_id' => $v->cliente_id]);
        $os = $this->em($u, fn () => OrdemServico::latest('id')->first());
        $this->post("/os/{$os->id}/itens", $p
            ? ['tipo' => 'peca', 'produto_id' => $p->id, 'quantidade' => 2, 'valor' => '']
            : ['tipo' => 'servico', 'descricao' => 'Revisão', 'quantidade' => 1, 'valor' => $valor]);

        return $this->em($u, fn () => OrdemServico::with('itens')->find($os->id));
    }

    // ---------- Orçamento público ----------

    public function test_link_expira_e_pode_ser_trocado(): void
    {
        $u = $this->novaEmpresa();
        $os = $this->osComItem($u);
        $this->assertSame(today()->addDays(15)->toDateString(), $os->token_expira_em->toDateString());
        auth()->logout();
        $this->get('/orcamento/'.$os->token)->assertOk();

        $this->travel(16)->days();
        $this->get('/orcamento/'.$os->token)->assertStatus(410)->assertSee('não vale mais');
        $this->post('/orcamento/'.$os->token, ['nome' => 'Ana', 'decisao' => 'aprovar', 'itens' => [$os->itens[0]->id], 'versao' => $os->versao]);
        $this->assertSame('pendente', $this->em($u, fn () => $os->itens()->first()->situacao));

        $antigo = $os->token;
        $this->actingAs($u)->post("/os/{$os->id}/acao/novo-link");
        $novo = $this->em($u, fn () => $os->fresh());
        $this->assertNotSame($antigo, $novo->token);
        auth()->logout();
        $this->get('/orcamento/'.$antigo)->assertNotFound();
        $this->get('/orcamento/'.$novo->token)->assertOk();
    }

    public function test_aceite_guarda_versao_exata_e_recusa_orcamento_alterado_depois(): void
    {
        $u = $this->novaEmpresa();
        $os = $this->osComItem($u);
        $versaoVista = $os->versao;

        // oficina muda o orçamento depois que o cliente abriu a página
        $this->actingAs($u)->put("/os/{$os->id}/desconto", ['desconto' => '10,00']);
        auth()->logout();
        $this->post('/orcamento/'.$os->token, ['nome' => 'Ana', 'decisao' => 'aprovar', 'itens' => [$os->itens[0]->id], 'versao' => $versaoVista])
            ->assertSessionHas('erro');
        $this->assertSame('pendente', $this->em($u, fn () => $os->itens()->first()->situacao));

        $atual = $this->em($u, fn () => $os->fresh());
        $this->post('/orcamento/'.$os->token, ['nome' => 'Ana Lima', 'decisao' => 'aprovar', 'itens' => [$os->itens[0]->id], 'versao' => $atual->versao], ['User-Agent' => 'Celular X'])
            ->assertSessionHas('ok');

        $aceite = $this->em($u, fn () => OrcamentoAceite::first());
        $this->assertSame(['link', 'Ana Lima (pelo link)', $atual->versao, 9000, 'Celular X'], [$aceite->canal, $aceite->nome, $aceite->versao, $aceite->total_centavos, $aceite->navegador]);
        $this->assertSame('aprovado', $aceite->itens[0]['decisao']);
        $this->assertTrue($aceite->integro());

        // adulterar o registro no banco é detectado
        \DB::table('orcamento_aceites')->where('id', $aceite->id)->update(['total_centavos' => 1]);
        $this->assertFalse($this->em($u, fn () => OrcamentoAceite::first())->integro());
    }

    public function test_os_cancelada_desativa_o_link(): void
    {
        $u = $this->novaEmpresa();
        $os = $this->osComItem($u);
        $this->actingAs($u)->post("/os/{$os->id}/acao/cancelar", ['motivo' => 'desistiu']);
        auth()->logout();
        $this->get('/orcamento/'.$os->token)->assertNotFound();
    }

    // ---------- Estoque ----------

    public function test_reserva_e_bloqueio_de_estoque_negativo(): void
    {
        $u = $this->novaEmpresa();
        Empresa::whereKey($u->empresa_id)->update(['estoque_negativo' => false]);
        $p = $this->em($u, function () {
            $p = Produto::create(['nome' => 'Filtro', 'preco_centavos' => 3000]);
            $p->movimentar(1, 'entrada', 'manual');

            return $p;
        });
        $os = $this->osComItem($u, p: $p);
        $this->post("/os/{$os->id}/acao/aprovar", ['itens' => [$os->itens[0]->id]]);
        $this->assertSame(2.0, $this->em($u, fn () => $p->reservado()));
        $this->assertSame(-1.0, $this->em($u, fn () => $p->disponivel()));

        $this->post("/os/{$os->id}/acao/concluir");
        $this->post("/os/{$os->id}/receber", ['pagamentos' => [['forma' => 'pix', 'valor' => '60,00']], 'entregar' => 1])
            ->assertSessionHas('erro');
        $this->assertSame(StatusOs::Pronta, $this->em($u, fn () => $os->fresh()->status));
        $this->assertSame(1.0, (float) $this->em($u, fn () => $p->fresh()->estoque));

        $this->em($u, fn () => $p->movimentar(5, 'entrada', 'manual'));
        $this->post("/os/{$os->id}/acao/entregar")->assertSessionHas('ok');
        $this->assertSame(4.0, (float) $this->em($u, fn () => $p->fresh()->estoque));
        $this->assertSame(0.0, $this->em($u, fn () => $p->reservado()));

        // reabrir devolve ao estoque
        $this->post("/os/{$os->id}/acao/reabrir", ['motivo' => 'entregue por engano'])->assertSessionHas('ok');
        $this->assertSame([StatusOs::Pronta, 6.0], $this->em($u, fn () => [$os->fresh()->status, (float) $p->fresh()->estoque]));
    }

    // ---------- Financeiro ----------

    public function test_estorno_de_recebimento_em_dinheiro_sai_do_caixa(): void
    {
        $u = $this->novaEmpresa();
        $this->actingAs($u)->post('/caixa/abrir', ['troco' => '0']);
        $conta = $this->em($u, fn () => ContaReceber::create(['descricao' => 'Venda', 'valor_centavos' => 5000, 'vencimento' => today(), 'forma' => 'prazo']));
        $this->post("/financeiro/receber/{$conta->id}/receber", ['forma' => 'dinheiro']);
        $this->assertSame(5000, $this->em($u, fn () => Caixa::aberto()->dinheiroEsperado()));

        $this->post("/financeiro/receber/{$conta->id}/estornar", ['motivo' => 'x'])->assertSessionHas('erro');
        $this->post("/financeiro/receber/{$conta->id}/estornar", ['motivo' => 'Cliente devolveu'])->assertSessionHas('ok');
        $this->assertSame(['estornada', 0], $this->em($u, fn () => [$conta->fresh()->status, Caixa::aberto()->dinheiroEsperado()]));
        $this->post("/financeiro/receber/{$conta->id}/estornar", ['motivo' => 'Duas vezes'])->assertSessionHas('erro');
    }

    public function test_exportacao_csv(): void
    {
        $u = $this->novaEmpresa();
        $this->em($u, fn () => ContaReceber::create(['descricao' => '=HYPERLINK("x")', 'valor_centavos' => 12345, 'vencimento' => today(), 'forma' => 'prazo']));
        $r = $this->actingAs($u)->get('/financeiro/exportar/receber');
        $r->assertOk();
        $csv = $r->streamedContent();
        $this->assertStringContainsString('123,45', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv, 'fórmula neutralizada');
        $mec = $this->usuarioNaMesmaEmpresa($u, Perfil::Mecanico);
        $this->actingAs($mec)->get('/financeiro/exportar/os')->assertForbidden();
    }

    // ---------- Agenda ----------

    public function test_agenda_respeita_capacidade(): void
    {
        $u = $this->novaEmpresa();
        Empresa::whereKey($u->empresa_id)->update(['agenda_capacidade' => 1]);
        $dados = ['nome' => 'A', 'inicio' => '2026-11-10 09:00', 'duracao_minutos' => 60, 'servico' => 'Revisão'];
        $this->actingAs($u)->post('/agenda', $dados)->assertSessionHasNoErrors();
        $this->post('/agenda', ['nome' => 'B', 'inicio' => '2026-11-10 09:30'] + $dados)->assertSessionHasErrors('inicio');
        $this->post('/agenda', ['nome' => 'C', 'inicio' => '2026-11-10 10:00'] + $dados)->assertSessionHasNoErrors(); // depois do 1º
        $this->post('/agenda', ['nome' => 'D', 'inicio' => '2026-11-10 09:30', 'encaixar' => 1] + $dados)->assertSessionHasNoErrors();
        $this->assertSame(3, $this->em($u, fn () => Agendamento::count()));
    }

    // ---------- Senha e duas etapas ----------

    public function test_recuperar_senha(): void
    {
        Notification::fake();
        $u = $this->novaEmpresa(email: 'ana@oficina.com.br');
        $this->post('/esqueci-senha', ['email' => 'naoexiste@x.com'])->assertSessionHas('ok');
        $this->post('/esqueci-senha', ['email' => 'ANA@oficina.com.br'])->assertSessionHas('ok');

        $token = null;
        Notification::assertSentTo($u, RedefinirSenha::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });
        $this->get('/redefinir-senha/'.$token.'?email=ana@oficina.com.br')->assertOk();
        $this->post('/redefinir-senha', ['token' => $token, 'email' => 'ana@oficina.com.br', 'password' => 'novasenha99', 'password_confirmation' => 'novasenha99'])
            ->assertRedirect('/entrar');
        $this->post('/entrar', ['email' => 'ana@oficina.com.br', 'password' => 'novasenha99'])->assertRedirect('/');
    }

    public function test_totp_e_login_em_duas_etapas(): void
    {
        // Vetores oficiais da RFC 6238 (segredo "12345678901234567890", SHA-1, 6 últimos dígitos)
        $seg = Totp::base32('12345678901234567890');
        $this->assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $seg);
        $this->assertSame('287082', Totp::codigo($seg, 59));
        $this->assertSame('081804', Totp::codigo($seg, 1111111109));
        $this->assertSame('005924', Totp::codigo($seg, 1234567890));
        $this->assertTrue(Totp::valido($seg, '287082', 59 + 30)); // tolera 30 s
        $this->assertFalse(Totp::valido($seg, '287082', 59 + 90));
        $this->assertSame('Hello!', Totp::decodificar(Totp::base32('Hello!')));

        $u = $this->novaEmpresa(email: 'dono@oficina.com.br');
        $this->actingAs($u)->get('/conta/seguranca')->assertOk();
        $segredo = session('novo_segredo');
        $this->post('/conta/seguranca/ativar', ['codigo' => '000000'])->assertSessionHasErrors('codigo');
        $this->post('/conta/seguranca/ativar', ['codigo' => Totp::codigo($segredo)])->assertSessionHas('codigos_novos');
        $codigos = session('codigos_novos');
        $this->assertTrue($u->fresh()->doisFatoresAtivo());
        $this->assertNotSame($segredo, \DB::table('users')->where('id', $u->id)->value('dois_fatores_segredo'), 'segredo criptografado');

        auth()->logout();
        $this->post('/entrar', ['email' => 'dono@oficina.com.br', 'password' => 'senha12345'])->assertRedirect('/entrar/codigo');
        $this->assertGuest();
        $this->post('/entrar/codigo', ['codigo' => '123456'])->assertSessionHasErrors('codigo');
        $this->post('/entrar/codigo', ['codigo' => Totp::codigo($segredo)])->assertRedirect('/');
        $this->assertAuthenticatedAs($u);

        // código de recuperação funciona uma vez
        auth()->logout();
        $this->post('/entrar', ['email' => 'dono@oficina.com.br', 'password' => 'senha12345']);
        $this->post('/entrar/codigo', ['codigo' => $codigos[0]])->assertRedirect('/');
        auth()->logout();
        $this->post('/entrar', ['email' => 'dono@oficina.com.br', 'password' => 'senha12345']);
        $this->post('/entrar/codigo', ['codigo' => $codigos[0]])->assertSessionHasErrors('codigo');
    }

    // ---------- Plataforma SaaS ----------

    public function test_cadastro_cria_oficina_em_teste_e_bloqueia_quando_vence(): void
    {
        $this->post('/cadastro', [
            'oficina' => 'Auto Mecânica Silva', 'nome' => 'José Silva', 'telefone' => '(19) 99999-0000', 'email' => 'jose@silva.com.br',
            'password' => 'senha12345', 'password_confirmation' => 'senha12345', 'plano' => 'essencial', 'aceite' => 1,
        ])->assertRedirect('/');
        $u = User::where('email', 'jose@silva.com.br')->first();
        $e = $u->empresa;
        $this->assertSame(['trial', 'essencial', Perfil::Dono], [$e->assinatura_status, $e->plano->slug, $u->perfil]);
        $this->assertSame(14, $e->diasDeTeste());
        $this->get('/os')->assertOk();

        $this->travel(15)->days();
        $this->get('/os')->assertRedirect('/assinatura');
        $this->get('/assinatura')->assertOk()->assertSee('O teste grátis terminou');
        $this->artisan('torque:assinaturas')->assertSuccessful();
        $this->assertSame('suspensa', $e->fresh()->assinatura_status);

        // assinar em modo demonstração reativa
        $this->post('/assinatura', ['plano' => 'profissional'])->assertSessionHas('ok');
        $this->assertSame(['ativa', 'profissional'], [$e->fresh()->assinatura_status, $e->fresh()->plano->slug]);
        $this->get('/os')->assertOk();
    }

    public function test_limite_de_usuarios_do_plano(): void
    {
        $u = $this->novaEmpresa();
        Empresa::whereKey($u->empresa_id)->update(['plano_id' => Plano::where('slug', 'essencial')->value('id')]);
        $this->usuarioNaMesmaEmpresa($u, Perfil::Mecanico);
        $this->usuarioNaMesmaEmpresa($u, Perfil::Consultor);
        $this->actingAs($u)->post('/usuarios', ['name' => 'Quarto', 'email' => 'q@x.com', 'perfil' => 'mecanico', 'password' => 'senha12345', 'ativo' => 1])
            ->assertSessionHas('erro');
        $this->assertSame(3, User::where('empresa_id', $u->empresa_id)->count());
    }

    public function test_webhook_asaas_autentica_e_e_idempotente(): void
    {
        config(['cobranca.asaas.webhook_token' => 'segredo-webhook']);
        $u = $this->novaEmpresa();
        Empresa::whereKey($u->empresa_id)->update(['cobranca_assinatura_id' => 'sub_123', 'assinatura_status' => 'trial']);
        $evento = ['id' => 'evt_1', 'event' => 'PAYMENT_RECEIVED', 'payment' => ['id' => 'pay_1', 'subscription' => 'sub_123', 'value' => 279.0, 'dueDate' => '2026-11-20', 'invoiceUrl' => 'https://asaas/i/1']];

        $this->postJson('/webhooks/asaas', $evento)->assertStatus(401);
        $this->postJson('/webhooks/asaas', $evento, ['asaas-access-token' => 'errado'])->assertStatus(401);
        $this->postJson('/webhooks/asaas', $evento, ['asaas-access-token' => 'segredo-webhook'])->assertOk();
        $this->postJson('/webhooks/asaas', $evento, ['asaas-access-token' => 'segredo-webhook'])->assertOk(); // repetido

        $e = Empresa::find($u->empresa_id);
        $this->assertSame(['ativa', '2026-12-20'], [$e->assinatura_status, $e->proximo_vencimento->toDateString()]);
        $faturas = $this->em($u, fn () => FaturaPlataforma::all());
        $this->assertCount(1, $faturas);
        $this->assertSame([27900, 'paga'], [$faturas[0]->valor_centavos, $faturas[0]->status]);

        $atraso = ['id' => 'evt_2', 'event' => 'PAYMENT_OVERDUE', 'payment' => ['id' => 'pay_2', 'subscription' => 'sub_123', 'value' => 279.0, 'dueDate' => now()->subDays(10)->toDateString()]];
        $this->postJson('/webhooks/asaas', $atraso, ['asaas-access-token' => 'segredo-webhook'])->assertOk();
        $this->assertSame('inadimplente', $e->fresh()->assinatura_status);
        $this->artisan('torque:assinaturas');
        $this->assertSame('suspensa', $e->fresh()->assinatura_status);
    }

    public function test_assinatura_pelo_asaas_envia_dados_certos(): void
    {
        config(['cobranca.provedor' => 'asaas', 'cobranca.asaas.chave' => 'chave-asaas', 'cobranca.asaas.url' => 'https://api-sandbox.asaas.com/v3']);
        Http::fake([
            '*/customers' => Http::response(['id' => 'cus_9'], 200),
            '*/subscriptions' => Http::response(['id' => 'sub_9'], 200),
        ]);
        $u = $this->novaEmpresa();
        $this->actingAs($u)->post('/assinatura', ['plano' => 'rede'])->assertSessionHas('ok');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/subscriptions') && $r->hasHeader('access_token', 'chave-asaas')
            && $r['customer'] === 'cus_9' && $r['value'] == 449.0 && $r['cycle'] === 'MONTHLY' && $r['externalReference'] === 'empresa:'.$u->empresa_id);
        $e = $u->empresa->fresh();
        $this->assertSame(['cus_9', 'sub_9', 'trial'], [$e->cobranca_cliente_id, $e->cobranca_assinatura_id, $e->assinatura_status]);
    }

    public function test_painel_da_plataforma_exige_admin_com_duas_etapas(): void
    {
        $dono = $this->novaEmpresa();
        $this->actingAs($dono)->get('/plataforma')->assertForbidden();

        $admin = $this->novaEmpresa('Torque (plataforma)');
        $admin->forceFill(['admin_plataforma' => true])->save();
        $this->actingAs($admin)->get('/plataforma')->assertRedirect('/conta/seguranca');

        $admin->forceFill(['dois_fatores_segredo' => Totp::novoSegredo(), 'dois_fatores_ativo_em' => now()])->save();
        $this->actingAs($admin)->get('/plataforma')->assertOk()->assertSee('Oficina Teste');
        $this->get('/plataforma/empresas/'.$dono->empresa_id)->assertOk();
        $this->put('/plataforma/empresas/'.$dono->empresa_id, ['plano_id' => Plano::first()->id, 'assinatura_status' => 'suspensa', 'ativo' => 1])->assertSessionHas('ok');
        $this->actingAs($dono->fresh())->get('/os')->assertRedirect('/assinatura');
    }

    // ---------- Portal do cliente ----------

    public function test_portal_do_cliente(): void
    {
        $u = $this->novaEmpresa();
        $os = $this->osComItem($u);
        $c = $this->em($u, fn () => Cliente::first());
        $this->actingAs($u)->post("/clientes/{$c->id}/portal");
        $token = $this->em($u, fn () => $c->fresh()->portal_token);
        $this->assertSame(40, strlen($token));

        auth()->logout();
        $this->get('/cliente/'.$token)->assertOk()->assertSee('Olá, Ana')->assertSee('Ver e aprovar orçamento');
        $this->get('/cliente/'.str_repeat('a', 40))->assertNotFound();

        $this->actingAs($u)->delete("/clientes/{$c->id}/portal");
        auth()->logout();
        $this->get('/cliente/'.$token)->assertNotFound();
    }
}
