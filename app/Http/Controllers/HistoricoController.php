<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\User;
use App\Support\Tenant;

/** "Ver histórico": quem mudou o quê, quando, de qual valor para qual valor. */
class HistoricoController extends Controller
{
    /** tipo na URL => [classe, nome no histórico, rota para voltar, título] */
    private const TIPOS = [
        'cliente' => [\App\Models\Cliente::class, 'Cliente', 'clientes.show'],
        'veiculo' => [\App\Models\Veiculo::class, 'Veiculo', 'veiculos.show'],
        'produto' => [\App\Models\Produto::class, 'Produto', 'produtos.show'],
        'fornecedor' => [\App\Models\Fornecedor::class, 'Fornecedor', 'fornecedores.edit'],
        'servico' => [\App\Models\Servico::class, 'Servico', 'servicos.index'],
        'usuario' => [User::class, 'User', 'usuarios.edit'],
        'os' => [\App\Models\OrdemServico::class, 'OrdemServico', 'os.show'],
        'conta-pagar' => [\App\Models\ContaPagar::class, 'ContaPagar', 'pagar.index'],
        'conta-receber' => [\App\Models\ContaReceber::class, 'ContaReceber', 'receber.index'],
    ];

    public const ROTULOS = [
        'nome' => 'Nome', 'name' => 'Nome', 'cpf_cnpj' => 'CPF/CNPJ', 'tipo_pessoa' => 'Tipo', 'ie' => 'Inscrição estadual', 'telefone' => 'Telefone',
        'whatsapp' => 'WhatsApp', 'aceita_whatsapp' => 'Aceita WhatsApp', 'email' => 'E-mail', 'cep' => 'CEP', 'logradouro' => 'Rua', 'numero' => 'Número',
        'complemento' => 'Complemento', 'bairro' => 'Bairro', 'cidade' => 'Cidade', 'uf' => 'UF', 'nascimento' => 'Nascimento', 'observacoes' => 'Observações',
        'placa' => 'Placa', 'marca' => 'Marca', 'modelo' => 'Modelo', 'versao' => 'Versão', 'motor' => 'Motor', 'renavam' => 'Renavam', 'chassi' => 'Chassi',
        'ano_fabricacao' => 'Ano fab.', 'ano_modelo' => 'Ano modelo', 'cor' => 'Cor', 'combustivel' => 'Combustível', 'km_atual' => 'Km', 'cliente_id' => 'Dono',
        'codigo' => 'Código', 'codigo_barras' => 'Código de barras', 'referencia_fabricante' => 'Referência', 'unidade' => 'Unidade', 'ncm' => 'NCM',
        'localizacao' => 'Local', 'custo_centavos' => 'Custo', 'ultimo_custo_centavos' => 'Último custo', 'preco_centavos' => 'Preço', 'estoque_minimo' => 'Estoque mínimo',
        'categoria_id' => 'Categoria', 'fabricante_id' => 'Fabricante', 'ativo' => 'Ativo', 'perfil' => 'Perfil', 'comissao_percentual' => 'Comissão %',
        'status' => 'Situação', 'desconto_centavos' => 'Desconto', 'total_centavos' => 'Total', 'diagnostico' => 'Diagnóstico', 'mecanico_id' => 'Mecânico',
        'defeito_relatado' => 'Relato', 'previsao_entrega' => 'Previsão', 'valor_centavos' => 'Valor', 'vencimento' => 'Vencimento', 'descricao' => 'Descrição',
        'categoria' => 'Categoria', 'motivo' => 'Motivo', 'tempo_minutos' => 'Tempo', 'garantia_dias' => 'Garantia', 'revisao_meses' => 'Revisão (meses)', 'revisao_km' => 'Revisão (km)',
    ];

    private const OCULTOS = ['id', 'empresa_id', 'loja_id', 'created_at', 'updated_at', 'deleted_at', 'password', 'remember_token', 'token', 'ultimo_acesso_em',
        'dois_fatores_segredo', 'dois_fatores_recuperacao', 'portal_token', 'checklist', 'versao', 'token_expira_em', 'estoque'];

    public function __invoke(string $tipo, int $id)
    {
        $this->authorize('auditoria.ver');
        abort_unless(isset(self::TIPOS[$tipo]), 404);
        [$classe, $nome, $rota] = self::TIPOS[$tipo];

        // Confere que o registro é da empresa atual (inclusive excluídos)
        $q = $classe === User::class ? User::daEmpresaAtual() : $classe::query();
        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($classe), true)) {
            $q->withTrashed();
        }
        $registro = $q->findOrFail($id);

        $entradas = Auditoria::with('usuario')->where('empresa_id', Tenant::id())->where('modelo', $nome)->where('modelo_id', $id)
            ->orderByDesc('id')->limit(300)->get()
            ->map(function ($a) {
                $campos = collect(array_keys(($a->depois ?? []) + ($a->antes ?? [])))
                    ->reject(fn ($c) => in_array($c, self::OCULTOS, true) || $c === 'motivo')->values();

                return ['quando' => $a->created_at, 'quem' => $a->usuario?->name ?? 'Sistema', 'acao' => $a->acao, 'ip' => $a->ip,
                    'motivo' => $a->depois['motivo'] ?? $a->antes['motivo'] ?? null,
                    'campos' => $campos->map(fn ($c) => [
                        'rotulo' => self::ROTULOS[$c] ?? $c,
                        'antes' => self::valor($c, $a->antes[$c] ?? null),
                        'depois' => self::valor($c, $a->depois[$c] ?? null),
                    ])->all()];
            });

        return view('historico', [
            'titulo' => $registro->nome ?? $registro->name ?? $registro->placa ?? $registro->descricao ?? ('#'.$id),
            'voltar' => $rota === 'servicos.index' || $rota === 'pagar.index' || $rota === 'receber.index' ? route($rota) : route($rota, $id),
            'entradas' => $entradas,
        ]);
    }

    private static function valor(string $campo, mixed $v): string
    {
        if ($v === null || $v === '') {
            return '—';
        }
        if (str_ends_with($campo, '_centavos')) {
            return \App\Support\Dinheiro::formatar((int) $v);
        }
        if (is_bool($v) || in_array($campo, ['ativo', 'aceita_whatsapp'], true)) {
            return $v ? 'sim' : 'não';
        }
        if (is_array($v)) {
            return json_encode($v, JSON_UNESCAPED_UNICODE);
        }

        return mb_strimwidth((string) $v, 0, 120, '…');
    }
}
