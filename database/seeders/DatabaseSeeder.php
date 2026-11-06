<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Loja;
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
        });
    }
}
