<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Fabricante;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Categorias e fabricantes de produtos (listas simples). */
class CadastroAuxiliarController extends Controller
{
    private function classe(string $tipo): string
    {
        return $tipo === 'categorias' ? Categoria::class : Fabricante::class;
    }

    public function index(string $tipo)
    {
        $this->authorize('estoque.ver');
        $classe = $this->classe($tipo);

        return view('produtos.auxiliar', [
            'tipo' => $tipo,
            'titulo' => $tipo === 'categorias' ? 'Categorias de produtos' : 'Fabricantes e marcas',
            'itens' => $classe::withCount('produtos')->orderByDesc('ativo')->orderBy('nome')->get(),
        ]);
    }

    public function store(Request $request, string $tipo)
    {
        $this->authorize('estoque.editar');
        $classe = $this->classe($tipo);
        $dados = $request->validate(['nome' => ['required', 'string', 'max:80', Rule::unique($tipo, 'nome')->where('empresa_id', Tenant::id())]],
            ['nome.unique' => 'Já existe com esse nome.']);
        $classe::create($dados + ['ativo' => true]);

        return back()->with('ok', $dados['nome'].' cadastrado.');
    }

    public function update(Request $request, string $tipo, int $id)
    {
        $this->authorize('estoque.editar');
        $item = $this->classe($tipo)::findOrFail($id);
        $dados = $request->validate(['nome' => ['required', 'string', 'max:80', Rule::unique($tipo, 'nome')->where('empresa_id', Tenant::id())->ignore($item->id)]],
            ['nome.unique' => 'Já existe com esse nome.']);
        $item->update(['nome' => $dados['nome'], 'ativo' => $request->boolean('ativo')]);

        return back()->with('ok', 'Salvo.');
    }
}
