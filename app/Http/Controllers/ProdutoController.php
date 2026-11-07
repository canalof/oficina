<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use App\Support\Dinheiro;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProdutoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('estoque.ver');
        $busca = $request->query('busca');
        $filtro = $request->query('filtro');

        return view('produtos.index', [
            'produtos' => Produto::busca($busca)
                ->when($filtro === 'minimo', fn ($q) => $q->abaixoDoMinimo())
                ->when($filtro === 'negativo', fn ($q) => $q->where('estoque', '<', 0))
                ->orderBy('nome')->paginate(40)->withQueryString(),
            'busca' => $busca,
            'filtro' => $filtro,
            'qtdMinimo' => Produto::abaixoDoMinimo()->count(),
            'valorEstoque' => (int) Produto::where('estoque', '>', 0)->selectRaw('sum(estoque * custo_centavos) as v')->value('v'),
        ]);
    }

    public function create()
    {
        $this->authorize('estoque.editar');

        return view('produtos.form', ['produto' => new Produto(['unidade' => 'UN', 'ativo' => true])]);
    }

    public function store(Request $request)
    {
        $this->authorize('estoque.editar');
        $dados = $this->validar($request);
        $inicial = (float) str_replace(',', '.', (string) $request->input('estoque_inicial'));
        $p = Produto::create($dados);
        if ($inicial > 0) {
            $p->movimentar($inicial, 'entrada', 'manual', null, $p->custo_centavos, 'Estoque inicial');
        }

        return redirect()->route('produtos.show', $p)->with('ok', $p->nome.' cadastrado.');
    }

    public function show(Produto $produto)
    {
        $this->authorize('estoque.ver');

        return view('produtos.show', ['produto' => $produto, 'movimentos' => $produto->movimentos()->with('user')->limit(60)->get()]);
    }

    public function edit(Produto $produto)
    {
        $this->authorize('estoque.editar');

        return view('produtos.form', ['produto' => $produto]);
    }

    public function update(Request $request, Produto $produto)
    {
        $this->authorize('estoque.editar');
        $produto->update($this->validar($request, $produto));

        return redirect()->route('produtos.show', $produto)->with('ok', 'Produto atualizado.');
    }

    public function destroy(Produto $produto)
    {
        $this->authorize('estoque.editar');
        $produto->delete();

        return redirect()->route('produtos.index')->with('ok', $produto->nome.' removido.');
    }

    /** Entrada manual ou ajuste de inventário (contagem). */
    public function movimentar(Request $request, Produto $produto)
    {
        $this->authorize('estoque.editar');
        $request->merge(['quantidade' => str_replace(',', '.', (string) $request->input('quantidade'))]);
        $dados = $request->validate([
            'tipo' => ['required', 'in:entrada,saida,contagem'],
            'quantidade' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'observacao' => ['nullable', 'string', 'max:255'],
        ], [], ['quantidade' => 'quantidade']);
        $q = (float) $dados['quantidade'];

        if ($dados['tipo'] === 'contagem') {
            $dif = round($q - (float) $produto->estoque, 3);
            if ($dif == 0) {
                return back()->with('ok', 'A contagem bate com o sistema.');
            }
            $produto->movimentar($dif, 'ajuste', 'ajuste', null, null, ($dados['observacao'] ?? null) ?: 'Contagem: '.Produto::formatarQtd($q));
        } else {
            if ($q <= 0) {
                return back()->withErrors(['quantidade' => 'Informe uma quantidade maior que zero.']);
            }
            $produto->movimentar($dados['tipo'] === 'entrada' ? $q : -$q, $dados['tipo'], 'manual', null, $dados['tipo'] === 'entrada' ? $produto->custo_centavos : null, $dados['observacao'] ?? null);
        }

        return back()->with('ok', 'Estoque atualizado: '.$produto->estoqueFormatado().'.');
    }

    private function validar(Request $request, ?Produto $produto = null): array
    {
        $request->merge([
            'custo_centavos' => Dinheiro::paraCentavos($request->input('custo')),
            'preco_centavos' => Dinheiro::paraCentavos($request->input('preco')),
            'estoque_minimo' => str_replace(',', '.', (string) ($request->input('estoque_minimo') ?: 0)),
            'ncm' => preg_replace('/\D/', '', (string) $request->input('ncm')) ?: null,
            'codigo_barras' => preg_replace('/\D/', '', (string) $request->input('codigo_barras')) ?: null,
            'unidade' => strtoupper((string) $request->input('unidade')) ?: 'UN',
        ]);
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'codigo' => ['nullable', 'string', 'max:30', Rule::unique('produtos', 'codigo')->where('empresa_id', Tenant::id())->whereNull('deleted_at')->ignore($produto?->id)],
            'codigo_barras' => ['nullable', 'digits_between:8,14'],
            'marca' => ['nullable', 'string', 'max:60'],
            'unidade' => ['required', 'string', 'max:6'],
            'ncm' => ['nullable', 'digits:8'],
            'localizacao' => ['nullable', 'string', 'max:40'],
            'custo_centavos' => ['integer', 'min:0'],
            'preco_centavos' => ['integer', 'min:0'],
            'estoque_minimo' => ['numeric', 'min:0'],
        ], ['codigo.unique' => 'Já existe um produto com este código.'], ['codigo_barras' => 'código de barras', 'ncm' => 'NCM']);
        $dados['ativo'] = $request->boolean('ativo', true);

        return $dados;
    }
}
