<?php

namespace Tests\Feature;

use App\Models\CnhConsulta;
use App\Models\CnhIntegracao;
use App\Models\ConferenciaCnh;
use App\Models\MobilidadeCondutor;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VioApiPrivadaTest extends TestCase
{
    use RefreshDatabase;

    private const ID = '550e8400-e29b-41d4-a716-446655440000';

    protected function setUp(): void
    {
        parent::setUp();
        config(['cnh.vio_privado.espera_ms' => 0, 'cnh.vio_privado.tentativas' => 3]);
        Http::preventStrayRequests();
    }

    private function em(User $u, callable $fn): mixed
    {
        return Tenant::com($u->empresa_id, $fn);
    }

    /** Salva a chave, testa (sem gastar crédito) e ativa, pela tela. */
    private function configurar(User $dono, string $chave = 'chave-da-oficina-123'): void
    {
        // Só o id fictício do teste de conexão: não esconde os fakes de cada teste.
        Http::fake(['vio.api.br/api/qrcode/result/00000000-0000-4000-8000-000000000000' => Http::response(['message' => 'Recurso não encontrado'], 404)]);
        $this->actingAs($dono)->put('/configuracoes/integracoes/cnh/vio_privado', [
            'credenciais' => ['api_key' => $chave], 'confirmacao_contrato' => '1', 'finalidades' => ['retirada', 'locacao'],
        ])->assertSessionHasNoErrors();
        $this->post('/configuracoes/integracoes/cnh/vio_privado/testar')->assertSessionHas('ok');
        $this->post('/configuracoes/integracoes/cnh/vio_privado/estado', ['acao' => 'ativar', 'confirmacao' => '1'])->assertSessionHas('ok');
    }

    private function condutor(User $u): MobilidadeCondutor
    {
        return $this->em($u, fn () => MobilidadeCondutor::create(['nome' => 'Maria Exemplo', 'categoria' => 'AB']));
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('cnh.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    }

    private function leitura(): array
    {
        return [
            'status' => 'completed', 'qr_type' => 'vio', 'cost' => 0.25,
            'vio_result' => ['Nome' => 'MARIA EXEMPLO', 'CPF' => '***.456.789-**', 'Cat. Hab.' => 'AB', 'Validade' => '15/03/2036', 'Nº Registro' => '01234567890'],
        ];
    }

    public function test_configura_testa_sem_gastar_e_ativa(): void
    {
        $dono = $this->novaEmpresa();
        $this->configurar($dono);

        $integracao = $this->em($dono, fn () => CnhIntegracao::where('provedor', 'vio_privado')->firstOrFail());
        $this->assertTrue($integracao->ativa);
        $this->assertSame('chave-da-oficina-123', $integracao->credenciais['api_key']);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->hasHeader('X-API-Key', 'chave-da-oficina-123'));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/api/qrcode/read'));
        $this->get('/configuracoes/integracoes/cnh')->assertOk()->assertSee('API Key da VIO API');
    }

    public function test_chave_recusada_nao_ativa(): void
    {
        $dono = $this->novaEmpresa();
        $this->actingAs($dono)->put('/configuracoes/integracoes/cnh/vio_privado', [
            'credenciais' => ['api_key' => 'chave-errada-000'], 'confirmacao_contrato' => '1', 'finalidades' => ['retirada'],
        ]);
        Http::fake(['vio.api.br/*' => Http::response(['message' => 'API Key inválida ou ausente'], 401)]);
        $this->post('/configuracoes/integracoes/cnh/vio_privado/testar')->assertSessionHas('erro');
        $this->assertFalse($this->em($dono, fn () => (bool) CnhIntegracao::firstOrFail()->teste_ok));
    }

    public function test_le_o_pdf_da_cnh_e_registra_a_consulta(): void
    {
        $dono = $this->novaEmpresa();
        $this->configurar($dono);
        $condutor = $this->condutor($dono);
        Http::fake([
            'vio.api.br/api/qrcode/read' => Http::response(['id' => self::ID, 'status' => 'processing']),
            'vio.api.br/api/qrcode/result/'.self::ID => Http::sequence()->push(['id' => self::ID, 'status' => 'processing'])->push($this->leitura()),
        ]);

        $this->post("/mobilidade/condutores/{$condutor->id}/consultar", [
            'provedor' => 'vio_privado', 'finalidade' => 'retirada', 'confirmacao' => '1', 'documento' => $this->pdf(),
        ])->assertSessionHas('ok', fn ($m) => str_contains($m, 'QR Code VIO lido') && str_contains($m, '15/03/2036'));

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/qrcode/read')
            && $r->hasHeader('X-API-Key', 'chave-da-oficina-123')
            && str_starts_with((string) $r['image'], 'data:application/pdf;base64,'));

        $consulta = $this->em($dono, fn () => CnhConsulta::firstOrFail());
        $this->assertSame('vio_privado', $consulta->provedor);
        $this->assertSame('documental', $consulta->evidencias['autenticidade']);
        $this->assertSame(['vigente', '2036-03-15'], [$consulta->evidencias['dimensoes']['validade']['estado'], $consulta->evidencias['dimensoes']['validade']['valor']]);
        $this->assertSame('AB', $consulta->evidencias['dimensoes']['categoria']['valor']);
        // Leitura documental não substitui a conferência registrada pela equipe.
        $this->assertSame(0, $this->em($dono, fn () => ConferenciaCnh::count()));
        $this->assertStringNotContainsString('456.789', json_encode($consulta->evidencias));

        $this->get("/mobilidade/condutores/{$condutor->id}")->assertOk()->assertSee('VIO API privada')->assertSee('Foto ou PDF da CNH (com o QR Code)');
    }

    public function test_preenche_o_cadastro_vazio_e_avisa_divergencia(): void
    {
        $dono = $this->novaEmpresa();
        $this->configurar($dono);
        $vazio = $this->em($dono, fn () => MobilidadeCondutor::create(['nome' => 'Maria Exemplo']));
        $antigo = $this->em($dono, fn () => MobilidadeCondutor::create(['nome' => 'Maria Exemplo', 'categoria' => 'B', 'validade' => '2030-01-10', 'registro_cnh' => '99999999999']));
        Http::fake([
            'vio.api.br/api/qrcode/read' => Http::response(['id' => self::ID, 'status' => 'processing']),
            'vio.api.br/api/qrcode/result/'.self::ID => Http::response($this->leitura()),
        ]);
        $dados = ['provedor' => 'vio_privado', 'finalidade' => 'locacao', 'confirmacao' => '1'];

        $this->post("/mobilidade/condutores/{$vazio->id}/consultar", $dados + ['documento' => $this->pdf()])
            ->assertSessionHas('ok', fn ($m) => str_contains($m, 'preenchido com: validade, categoria, registro da CNH'));
        $vazio = $this->em($dono, fn () => $vazio->fresh());
        $this->assertSame(['2036-03-15', 'AB', '01234567890'], [$vazio->validade->format('Y-m-d'), $vazio->categoria, $vazio->registro_cnh]);

        $this->post("/mobilidade/condutores/{$antigo->id}/consultar", $dados + ['documento' => $this->pdf()])
            ->assertSessionHas('ok', fn ($m) => str_contains($m, 'validade do cadastro (10/01/2030) é diferente') && str_contains($m, 'categoria do cadastro (B)'));
        $antigo = $this->em($dono, fn () => $antigo->fresh());
        $this->assertSame(['2030-01-10', 'B', '99999999999'], [$antigo->validade->format('Y-m-d'), $antigo->categoria, $antigo->registro_cnh]);

        // O registro da CNH não fica nas evidências da consulta
        $this->assertStringNotContainsString('01234567890', json_encode($this->em($dono, fn () => CnhConsulta::pluck('evidencias')->all())));
    }

    public function test_avisa_saldo_insuficiente_e_arquivo_sem_qr_vio(): void
    {
        $dono = $this->novaEmpresa();
        $this->configurar($dono);
        $condutor = $this->condutor($dono);
        $dados = ['provedor' => 'vio_privado', 'finalidade' => 'retirada', 'confirmacao' => '1'];

        Http::fake(['vio.api.br/api/qrcode/read' => Http::sequence()
            ->push(['message' => 'Saldo insuficiente'], 402)
            ->push(['id' => self::ID, 'status' => 'completed', 'qr_type' => 'qrcode', 'vio_result' => null])]);
        $this->post("/mobilidade/condutores/{$condutor->id}/consultar", $dados + ['documento' => $this->pdf()])
            ->assertSessionHas('erro', fn ($m) => str_contains($m, 'Saldo insuficiente'));

        $this->post("/mobilidade/condutores/{$condutor->id}/consultar", $dados + ['documento' => $this->pdf()])
            ->assertSessionHas('erro', fn ($m) => str_contains($m, 'Não foi encontrado QR Code VIO'));

        $this->post("/mobilidade/condutores/{$condutor->id}/consultar", $dados + ['documento' => UploadedFile::fake()->createWithContent('cnh.txt', 'texto')])
            ->assertSessionHasErrors('documento');
        $this->assertSame(['inconclusiva', 'inconclusiva'], $this->em($dono, fn () => CnhConsulta::pluck('status')->all()));
    }

    public function test_isolamento_entre_empresas(): void
    {
        $a = $this->novaEmpresa('Oficina A');
        $this->configurar($a);
        $b = $this->novaEmpresa('Oficina B');
        $condutorB = $this->condutor($b);
        Http::fake();

        // B não usa a chave de A nem vê a integração dela
        $this->actingAs($b)->post("/mobilidade/condutores/{$condutorB->id}/consultar", [
            'provedor' => 'vio_privado', 'finalidade' => 'retirada', 'confirmacao' => '1', 'documento' => $this->pdf(),
        ])->assertSessionHas('erro', fn ($m) => str_contains($m, 'não está ativa'));
        Http::assertNothingSent();
        $this->assertNull($this->em($b, fn () => CnhIntegracao::first()));

        // A não consulta condutor de B
        $this->actingAs($a)->post("/mobilidade/condutores/{$condutorB->id}/consultar", [
            'provedor' => 'vio_privado', 'finalidade' => 'retirada', 'confirmacao' => '1', 'documento' => $this->pdf(),
        ])->assertNotFound();
    }
}
