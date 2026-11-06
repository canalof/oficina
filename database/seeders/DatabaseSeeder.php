<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Loja;
use App\Models\OrdemServico;
use App\Models\Servico;
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

            $this->catalogoEOrdens();
        });
    }

    private function catalogoEOrdens(): void
    {
        $catalogo = [
            ['Troca de óleo e filtro', 9000, 30], ['Alinhamento e balanceamento', 12000, 60],
            ['Revisão de freios', 15000, 60], ['Troca de pastilhas de freio (eixo)', 9000, 45],
            ['Diagnóstico com scanner', 12000, 30], ['Higienização do ar-condicionado', 15000, 40],
            ['Troca de correia dentada', 45000, 180], ['Troca de amortecedores (par)', 22000, 120],
        ];
        $servicos = [];
        foreach ($catalogo as [$nome, $preco, $min]) {
            $servicos[$nome] = Servico::create(['nome' => $nome, 'preco_centavos' => $preco, 'tempo_minutos' => $min]);
        }
        $mecanico = User::where('email', 'mecanico@demo.com.br')->first();
        $consultor = User::where('email', 'recepcao@demo.com.br')->first();

        $item = fn (OrdemServico $os, string $desc, int $valor, string $tipo = 'servico', float $qtd = 1, ?string $situacao = 'pendente') => $os->itens()->create([
            'tipo' => $tipo, 'servico_id' => $servicos[$desc]->id ?? null, 'descricao' => $desc, 'quantidade' => $qtd,
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
        $item($os, 'Filtro de óleo', 3500, 'peca');
        $item($os, 'Troca de correia dentada', 45000);
        $os->recalcular();
        $os->marcarEnviado();
        $os->aprovar([$os->itens[0]->id, $os->itens[1]->id, $os->itens[2]->id], 'João Pedro (pelo link)');
        $os->iniciar(); $os->concluir(); $os->entregar();
        $os->forceFill(['created_at' => now()->subDays(40), 'entregue_em' => now()->subDays(39)])->saveQuietly();

        // Em serviço
        $os = $nova('FTR4E21', ['defeito_relatado' => 'Barulho na roda dianteira ao frear.', 'diagnostico' => 'Pastilhas no limite e discos com desgaste irregular.', 'previsao_entrega' => now()->setTime(17, 30)]);
        $item($os, 'Troca de pastilhas de freio (eixo)', 9000);
        $item($os, 'Jogo de pastilhas dianteiras', 18900, 'peca');
        $item($os, 'Alinhamento e balanceamento', 12000);
        $os->recalcular();
        $os->marcarEnviado();
        $os->aprovar(null, 'Mariana Souza (pelo link)');
        $os->iniciar();

        // Aguardando aprovação
        $os = $nova('RQA2B34', ['defeito_relatado' => 'Ar-condicionado com cheiro ruim e gelando pouco.', 'previsao_entrega' => now()->addDay()->setTime(12, 0)]);
        $item($os, 'Higienização do ar-condicionado', 15000);
        $item($os, 'Filtro de cabine', 6500, 'peca');
        $item($os, 'Carga de gás do ar-condicionado', 22000);
        $os->recalcular();
        $os->marcarEnviado();

        // Orçamento recém-aberto
        $os = $nova('GHT5C67', ['defeito_relatado' => 'Luz da injeção acesa.']);
        $item($os, 'Diagnóstico com scanner', 12000);
        $os->recalcular();
    }
}
