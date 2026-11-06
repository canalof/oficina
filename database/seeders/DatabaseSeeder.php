<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Loja;
use App\Models\OrdemServico;
use App\Models\Servico;
use App\Models\Produto;
use App\Models\Fornecedor;
use App\Models\ContaPagar;
use App\Models\Agendamento;
use App\Models\Caixa;
use App\Models\User;
use App\Models\Veiculo;
use App\Models\VeiculoDono;
use App\Support\Perfil;
use App\Support\Tenant;
use Illuminate\Database\Seeder;

/**
 * Dados de demonstração (php artisan migrate --seed).
 * Login: dono@demo.com.br / senha12345
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::create([
            'nome_fantasia' => 'Autocenter Demonstração',
            'razao_social' => 'Autocenter Demonstração Ltda',
            'cidade' => 'Sumaré',
            'uf' => 'SP',
            'telefone' => '(19) 3800-0000',
        ]);

        Tenant::com($empresa->id, function () use ($empresa) {
            $loja = Loja::create(['nome' => 'Matriz', 'cidade' => 'Sumaré', 'uf' => 'SP']);

            $usuarios = [
                ['Edson Nunes', 'dono@demo.com.br', Perfil::Dono],
                ['Carla Recepção', 'recepcao@demo.com.br', Perfil::Consultor],
                ['Marcos Mecânico', 'mecanico@demo.com.br', Perfil::Mecanico],
            ];
            foreach ($usuarios as [$nome, $email, $perfil]) {
                User::create(['empresa_id' => $empresa->id, 'loja_id' => $loja->id, 'name' => $nome, 'email' => $email, 'perfil' => $perfil, 'ativo' => true, 'password' => 'senha12345']);
            }

            $clientes = [
                ['Mariana Souza', '52998224725', '(19) 99876-5432', [['FTR4E21', 'Hyundai', 'HB20', '1.0 Comfort', 2020, 2021, 'Prata', 48210]]],
                ['Construtora Horizonte Ltda', '11222333000181', '(19) 3456-7890', [['RQA2B34', 'Fiat', 'Strada', 'Freedom 1.3 CD', 2022, 2023, 'Branco', 61500], ['GHT5C67', 'Volkswagen', 'Saveiro', 'Robust 1.6 CS', 2021, 2021, 'Branco', 88320]]],
                ['João Pedro Alves', '39053344705', '(19) 98111-2233', [['ABC1234', 'Volkswagen', 'Gol', '1.0 MPI', 2015, 2016, 'Preto', 142300]]],
                ['Fernanda Lima', null, '(19) 99222-1100', []],
            ];
            foreach ($clientes as [$nome, $doc, $fone, $carros]) {
                $c = Cliente::create([
                    'nome' => $nome, 'cpf_cnpj' => $doc, 'tipo_pessoa' => $doc && strlen($doc) === 14 ? 'PJ' : 'PF',
                    'whatsapp' => $fone, 'aceita_whatsapp' => true, 'cidade' => 'Sumaré', 'uf' => 'SP',
                ]);
                foreach ($carros as [$placa, $marca, $modelo, $versao, $af, $am, $cor, $km]) {
                    $v = Veiculo::create([
                        'cliente_id' => $c->id, 'placa' => $placa, 'marca' => $marca, 'modelo' => $modelo, 'versao' => $versao,
                        'ano_fabricacao' => $af, 'ano_modelo' => $am, 'cor' => $cor, 'combustivel' => 'Flex', 'km_atual' => $km,
                    ]);
                    VeiculoDono::create(['veiculo_id' => $v->id, 'cliente_id' => $c->id, 'inicio' => now()->subMonths(8)->toDateString()]);
                }
            }

            $this->estoque();
            $this->catalogoEOrdens();
            $this->financeiroEAgenda();
        });
    }

    private function catalogoEOrdens(): void
    {
        $catalogo = [
            ['Troca de óleo e filtro', 9000, 30, 6, 10000], ['Alinhamento e balanceamento', 12000, 60],
            ['Revisão de freios', 15000, 60], ['Troca de pastilhas de freio (eixo)', 9000, 45],
            ['Diagnóstico com scanner', 12000, 30], ['Higienização do ar-condicionado', 15000, 40],
            ['Troca de correia dentada', 45000, 180], ['Troca de amortecedores (par)', 22000, 120],
        ];
        $servicos = [];
        foreach ($catalogo as $c) {
            [$nome, $preco, $min] = $c;
            $servicos[$nome] = Servico::create(['nome' => $nome, 'preco_centavos' => $preco, 'tempo_minutos' => $min, 'revisao_meses' => $c[3] ?? null, 'revisao_km' => $c[4] ?? null]);
        }
        User::where('email', 'mecanico@demo.com.br')->update(['comissao_percentual' => 10]);
        $mecanico = User::where('email', 'mecanico@demo.com.br')->first();
        $consultor = User::where('email', 'recepcao@demo.com.br')->first();

        $produtos = Produto::pluck('id', 'nome');
        $item = fn (OrdemServico $os, string $desc, int $valor, string $tipo = 'servico', float $qtd = 1, ?string $situacao = 'pendente') => $os->itens()->create([
            'tipo' => $tipo, 'servico_id' => $servicos[$desc]->id ?? null, 'produto_id' => $produtos[$desc] ?? null, 'descricao' => $desc, 'quantidade' => $qtd,
            'valor_unitario_centavos' => $valor, 'situacao' => $situacao,
        ]);
        $nova = function (string $placa, array $extra = []) use ($consultor, $mecanico) {
            $v = Veiculo::where('placa', $placa)->first();

            return OrdemServico::abrir(array_merge([
                'veiculo_id' => $v->id, 'cliente_id' => $v->cliente_id, 'consultor_id' => $consultor->id, 'mecanico_id' => $mecanico->id,
                'km_entrada' => $v->km_atual, 'combustivel' => 2, 'garantia_dias' => 90,
                'checklist' => ['parachoque_dianteiro' => ['estado' => 'avaria', 'obs' => 'risco no canto esquerdo'], 'pneus' => ['estado' => 'ok', 'obs' => null], 'estepe' => ['estado' => 'ok', 'obs' => null]],
            ], $extra));
        };

        // Entregue no mês passado (histórico)
        $os = $nova('ABC1234', ['defeito_relatado' => 'Revisão dos 140 mil.']);
        $item($os, 'Troca de óleo e filtro', 9000);
        $item($os, 'Óleo 5W30 sintético (litro)', 4500, 'peca', 4);
        $item($os, 'Filtro de óleo PSL55', 3500, 'peca');
        $item($os, 'Troca de correia dentada', 45000);
        $os->recalcular();
        $os->marcarEnviado();
        $os->aprovar([$os->itens[0]->id, $os->itens[1]->id, $os->itens[2]->id], 'João Pedro (pelo link)');
        $os->iniciar(); $os->concluir();
        $os->forceFill(['recebido_em' => now()->subDays(39)])->saveQuietly();
        $os->contasReceber()->create(['cliente_id' => $os->cliente_id, 'descricao' => 'OS '.$os->numeroFormatado(), 'valor_centavos' => $os->total_centavos, 'vencimento' => now()->subDays(39), 'forma' => 'pix', 'status' => 'recebida', 'recebido_em' => now()->subDays(39), 'forma_recebida' => 'pix']);
        $os->entregar();
        $os->forceFill(['created_at' => now()->subDays(40), 'entregue_em' => now()->subDays(39)])->saveQuietly();

        // Em serviço
        $os = $nova('FTR4E21', ['defeito_relatado' => 'Barulho na roda dianteira ao frear.', 'diagnostico' => 'Pastilhas no limite e discos com desgaste irregular.', 'previsao_entrega' => now()->setTime(17, 30)]);
        $item($os, 'Troca de pastilhas de freio (eixo)', 9000);
        $item($os, 'Pastilha de freio dianteira HB20 (jogo)', 18900, 'peca');
        $item($os, 'Alinhamento e balanceamento', 12000);
        $os->recalcular();
        $os->marcarEnviado();
        $os->aprovar(null, 'Mariana Souza (pelo link)');
        $os->iniciar();

        // Aguardando aprovação
        $os = $nova('RQA2B34', ['defeito_relatado' => 'Ar-condicionado com cheiro ruim e gelando pouco.', 'previsao_entrega' => now()->addDay()->setTime(12, 0)]);
        $item($os, 'Higienização do ar-condicionado', 15000);
        $item($os, 'Filtro de cabine Strada', 6500, 'peca');
        $item($os, 'Carga de gás do ar-condicionado', 22000);
        $os->recalcular();
        $os->marcarEnviado();

        // Orçamento recém-aberto
        $os = $nova('GHT5C67', ['defeito_relatado' => 'Luz da injeção acesa.']);
        $item($os, 'Diagnóstico com scanner', 12000);
        $os->recalcular();
    }

    private function estoque(): void
    {
        $f = Fornecedor::create(['nome' => 'Distribuidora Auto Peças Campinas', 'cpf_cnpj' => '11444777000161', 'telefone' => '(19) 3232-1000', 'cidade' => 'Campinas', 'uf' => 'SP', 'contato' => 'Rogério']);
        $lista = [
            // nome, código, NCM, unidade, custo, preço, estoque, mínimo, local
            ['Óleo 5W30 sintético (litro)', 'OL5W30', '27101932', 'L', 2600, 4500, 38, 20, 'A1'],
            ['Filtro de óleo PSL55', 'FO-PSL55', '84212300', 'UN', 1500, 3500, 6, 5, 'A2'],
            ['Pastilha de freio dianteira HB20 (jogo)', 'PF-HB20', '68138190', 'JG', 9800, 18900, 2, 3, 'B1'],
            ['Filtro de cabine Strada', 'FC-STR', '84213990', 'UN', 3100, 6500, 4, 2, 'A3'],
            ['Fluido de freio DOT 4 500ml', 'FF-DOT4', '38190000', 'UN', 1800, 3900, 12, 4, 'C1'],
            ['Palheta limpador 20"', 'PL-20', '85129000', 'UN', 1600, 3900, 0, 4, 'C2'],
        ];
        foreach ($lista as [$nome, $cod, $ncm, $un, $custo, $preco, $qtd, $min, $local]) {
            $p = Produto::create(['nome' => $nome, 'codigo' => $cod, 'ncm' => $ncm, 'unidade' => $un, 'custo_centavos' => $custo, 'preco_centavos' => $preco, 'estoque_minimo' => $min, 'localizacao' => $local]);
            if ($qtd) {
                $p->movimentar($qtd, 'entrada', 'manual', null, $custo, 'Estoque inicial');
            }
        }
        ContaPagar::create(['fornecedor_id' => $f->id, 'categoria' => 'Peças e fornecedores', 'descricao' => 'NF 4521 Distribuidora Auto Peças', 'valor_centavos' => 184300, 'vencimento' => today()->addDays(5)]);
    }

    private function financeiroEAgenda(): void
    {
        $dono = User::where('email', 'dono@demo.com.br')->first();
        Caixa::create(['aberto_por' => $dono->id, 'aberto_em' => now()->setTime(8, 0), 'saldo_inicial_centavos' => 20000]);
        ContaPagar::create(['categoria' => 'Aluguel', 'descricao' => 'Aluguel do galpão', 'valor_centavos' => 350000, 'vencimento' => today()->startOfMonth()->addDays(9)]);
        ContaPagar::create(['categoria' => 'Energia e água', 'descricao' => 'Conta de luz', 'valor_centavos' => 61240, 'vencimento' => today()->subDay()]);

        $mariana = Cliente::where('nome', 'Mariana Souza')->first();
        $joao = Cliente::where('nome', 'João Pedro Alves')->first();
        Agendamento::create(['cliente_id' => $mariana->id, 'nome' => $mariana->nome, 'telefone' => $mariana->whatsapp, 'placa' => 'FTR4E21', 'veiculo_id' => Veiculo::where('placa', 'FTR4E21')->value('id'), 'inicio' => today()->addDay()->setTime(9, 0), 'servico' => 'Troca de óleo', 'status' => 'confirmado', 'user_id' => $dono->id]);
        Agendamento::create(['cliente_id' => $joao->id, 'nome' => $joao->nome, 'telefone' => $joao->whatsapp, 'placa' => 'ABC1234', 'veiculo_id' => Veiculo::where('placa', 'ABC1234')->value('id'), 'inicio' => today()->setTime(14, 30), 'servico' => 'Barulho na suspensão', 'user_id' => $dono->id]);
        Agendamento::create(['nome' => 'Ricardo (indicação)', 'telefone' => '(19) 99700-1122', 'inicio' => today()->addDays(2)->setTime(10, 0), 'duracao_minutos' => 120, 'servico' => 'Revisão completa', 'user_id' => $dono->id]);

        // O lembrete de troca de óleo da OS entregue (criado na entrega) fica para a próxima semana na demonstração
        \App\Models\Lembrete::query()->update(['data_prevista' => today()->addDays(4)]);
    }
}
