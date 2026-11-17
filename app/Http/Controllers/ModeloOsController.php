<?php

namespace App\Http\Controllers;

use App\Models\ModeloOs;
use App\Models\OrdemServico;
use App\Models\Produto;
use App\Models\Servico;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ModeloOsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('servicos.gerenciar');

        return view('servicos.modelos', [
            'modelos' => ModeloOs::orderBy('nome')->paginate(25),
            'editando' => $request->filled('editar') ? ModeloOs::findOrFail($request->query('editar')) : null,
            'servicos' => Servico::where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
            'produtos' => Produto::where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'codigo']),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('servicos.gerenciar');
        ModeloOs::create($this->validar($request));

        return redirect()->route('modelos-os.index')->with('ok', 'Modelo de OS criado.');
    }

    public function update(Request $request, ModeloOs $modelo)
    {
        $this->authorize('servicos.gerenciar');
        $modelo->update($this->validar($request, $modelo));

        return redirect()->route('modelos-os.index')->with('ok', 'Modelo de OS atualizado.');
    }

    public function aplicar(OrdemServico $os, ModeloOs $modelo)
    {
        $this->authorize('os.editar');

        $erro = DB::transaction(function () use ($os, $modelo) {
            $atual = OrdemServico::whereKey($os->id)->lockForUpdate()->firstOrFail();
            $modelo = ModeloOs::whereKey($modelo->id)->lockForUpdate()->firstOrFail();
            if (! $atual->status->aceitaItens() || ! $modelo->ativo) {
                return 'Esta OS ou modelo não permite incluir itens agora.';
            }
            $servicos = Servico::where('ativo', true)->whereIn('id', $modelo->servico_ids)->get();
            $produtos = Produto::where('ativo', true)->whereIn('id', $modelo->produto_ids)->get();
            if ($servicos->count() !== count($modelo->servico_ids) || $produtos->count() !== count($modelo->produto_ids)) {
                return 'O modelo contém itens removidos ou inativos. Revise o modelo antes de aplicar.';
            }
            $itens = $servicos->map(fn ($item) => $this->item($item, 'servico', 'servico_id'))
                ->concat($produtos->map(fn ($item) => $this->item($item, 'peca', 'produto_id')));
            $atual->itens()->createMany($itens->all());
            $atual->recalcular();
            $atual->alterouOrcamento();
            $atual->registrar('Modelo de OS aplicado: '.$modelo->nome);

            return null;
        }, 3);

        return redirect()->route('os.show', $os)->withFragment('itens')->with(
            $erro ? 'erro' : 'ok', $erro ?? 'Modelo aplicado. Confira as quantidades, os valores e a aprovação dos itens.',
        );
    }

    private function item(Servico|Produto $item, string $tipo, string $campo): array
    {
        return [
            'tipo' => $tipo, $campo => $item->id, 'descricao' => $item->nome,
            'quantidade' => 1, 'valor_unitario_centavos' => $item->preco_centavos, 'situacao' => 'pendente',
        ];
    }

    private function validar(Request $request, ?ModeloOs $modelo = null): array
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120', Rule::unique('modelos_os', 'nome')->where('empresa_id', Tenant::id())->ignore($modelo?->id)],
            'servico_ids' => ['required_without:produto_ids', 'array', 'max:20'],
            'servico_ids.*' => ['required', 'integer', 'distinct', Rule::exists('servicos', 'id')->where('empresa_id', Tenant::id())->where('ativo', true)->whereNull('deleted_at')],
            'produto_ids' => ['required_without:servico_ids', 'array', 'max:20'],
            'produto_ids.*' => ['required', 'integer', 'distinct', Rule::exists('produtos', 'id')->where('empresa_id', Tenant::id())->where('ativo', true)->whereNull('deleted_at')],
            'ativo' => ['sometimes', 'boolean'],
        ], [
            'servico_ids.required_without' => 'Selecione ao menos um serviço ou produto.',
            'produto_ids.required_without' => 'Selecione ao menos um serviço ou produto.',
        ]);

        return $dados + ['servico_ids' => [], 'produto_ids' => [], 'ativo' => true];
    }
}
