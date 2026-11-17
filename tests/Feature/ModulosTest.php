<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use App\Support\Perfil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModulosTest extends TestCase
{
    use RefreshDatabase;

    /** Oficina nova "de verdade": sem módulos opcionais. */
    private function oficinaBasica(string $nome = 'Oficina Básica'): User
    {
        $u = $this->novaEmpresa($nome);
        $u->empresa->forceFill(['modulos' => [], 'modulo_locacao' => false])->save();

        return $u->fresh();
    }

    private function salvarModulos(User $dono, array $modulos)
    {
        $e = $dono->empresa;

        return $this->actingAs($dono)->put('/empresa', [
            'nome_fantasia' => $e->nome_fantasia, 'regime_tributario' => 1, 'cfop_padrao' => '5102', 'csosn_padrao' => '102',
            'item_lista_servico' => '14.01', 'aliquota_iss' => '0', 'fiscal_ambiente' => 'homologacao', 'agenda_capacidade' => 5,
            'orcamento_validade_dias' => 10, 'modulos_enviados' => '1', 'modulos' => $modulos,
        ]);
    }

    public function test_oficina_basica_ve_so_o_essencial(): void
    {
        $u = $this->oficinaBasica();
        $menu = $this->actingAs($u)->get('/')->assertOk();
        foreach (['Ordens de serviço', 'Clientes', 'Agenda', 'Produtos', 'Caixa', 'Contas a receber', 'Empresa e módulos', 'Ajuda'] as $item) {
            $menu->assertSee($item);
        }
        foreach (['Bancos', 'Comissões', 'Notas fiscais', 'Condutores e retirada', 'Reservas e frota', 'Integrações de CNH', 'Lembretes de revisão'] as $item) {
            $menu->assertDontSee($item);
        }

        foreach (['/financeiro/bancos', '/financeiro/pix', '/financeiro/comissoes', '/notas', '/mobilidade', '/locacao', '/condutores/cnh', '/configuracoes/integracoes/cnh'] as $url) {
            $this->get($url)->assertForbidden();
        }
        // Lembretes continuam, agora a partir da Agenda
        $this->get('/agenda')->assertOk()->assertSee('Lembretes de revisão');
        $this->get('/lembretes')->assertOk();
    }

    public function test_dono_liga_e_desliga_modulos_e_dados_ficam(): void
    {
        $u = $this->oficinaBasica();
        $this->salvarModulos($u, ['bancos', 'fiscal'])->assertSessionHasNoErrors();
        $e = $u->empresa->fresh();
        $this->assertSame([true, true, false, false], [$e->temModulo('bancos'), $e->temModulo('fiscal'), $e->temModulo('comissoes'), $e->temModulo('condutores')]);
        $this->actingAs($u->fresh())->get('/financeiro/bancos')->assertOk();
        $this->get('/')->assertSee('Bancos')->assertSee('Notas fiscais')->assertDontSee('Comissões');
        $this->get('/empresa')->assertOk()->assertSee('Dados fiscais');

        $this->salvarModulos($u->fresh(), [])->assertSessionHasNoErrors();
        $this->actingAs($u->fresh())->get('/financeiro/bancos')->assertForbidden();
        // Sem notas fiscais, os dados fiscais somem da tela mas ficam guardados
        $this->get('/empresa')->assertOk()->assertDontSee('Dados fiscais');
        $this->assertSame('5102', $u->empresa->fresh()->cfop_padrao);
    }

    public function test_locacao_liga_condutores_junto(): void
    {
        $u = $this->oficinaBasica();
        $this->salvarModulos($u, ['locacao'])->assertSessionHasNoErrors();
        $e = $u->empresa->fresh();
        $this->assertTrue($e->modulo_locacao);
        $this->assertSame(['condutores'], $e->modulos);
        $this->actingAs($u->fresh())->get('/mobilidade')->assertOk();
        $this->get('/locacao')->assertOk();
    }

    public function test_so_o_dono_muda_modulos_e_cada_empresa_tem_os_seus(): void
    {
        $a = $this->oficinaBasica('Oficina A');
        $b = $this->oficinaBasica('Oficina B');
        $this->salvarModulos($a, ['bancos']);
        $this->assertTrue($a->empresa->fresh()->temModulo('bancos'));
        $this->assertFalse($b->empresa->fresh()->temModulo('bancos'));
        $this->actingAs($b->fresh())->get('/financeiro/bancos')->assertForbidden();

        $mecanico = $this->usuarioNaMesmaEmpresa($a, Perfil::Mecanico);
        $this->salvarModulos($mecanico, ['bancos', 'comissoes', 'fiscal'])->assertForbidden();
        $this->assertFalse($a->empresa->fresh()->temModulo('comissoes'));
    }

    public function test_grupo_do_menu_abre_so_onde_estou(): void
    {
        $u = $this->novaEmpresa();
        $html = $this->actingAs($u)->get('/produtos')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<details class="group\/menu mt-2"\s+open\s*>\s*<summary[^>]*>Estoque/', $html);
        $this->assertDoesNotMatchRegularExpression('/<details class="group\/menu mt-2"\s+open\s*>\s*<summary[^>]*>Dinheiro/', $html);
    }

    public function test_empresas_existentes_ficam_com_o_que_ja_usam(): void
    {
        $nova = Empresa::create(['nome_fantasia' => 'Recém-cadastrada']);
        $this->assertFalse($nova->temModulo('bancos'));
        $this->assertFalse($nova->temModulo('condutores'));
    }
}
