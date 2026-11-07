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

        $categoria = $request->query('categoria');

        return view('produtos.index', [
            'categorias' => \App\Models\Categoria::where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
            'categoria' => $categoria,
            'produtos' => Produto::with('fabricante')->busca($busca)
                ->when($categoria, fn ($q) => $q->where('categoria_id', $categoria))
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

        return view('produtos.form', ['produto' => new Produto(['unidade' => 'UN', 'ativo' => true])] + $this->listas());
    }

    public function store(Request $request)
    {
        $this->authorize('estoque.editar');
        $dados = $this->validar($request);
        if (! $request->boolean('confirmar_duplicado') && ($parecidos = \App\Support\Duplicados::produtos($dados))->isNotEmpty()) {
            return back()->withInput()->with('duplicados', $parecidos->map(fn ($p) => ['nome' => $p->nome, 'url' => route('produtos.show', $p), 'info' => null])->all());
        }
        $inicial = (float) str_replace(',', '.', (string) $request->input('estoque_inicial'));
        $custo = $dados['custo_centavos'];
        $p = Produto::create($dados);
        if ($inicial > 0) {
            $p->movimentar($inicial, 'entrada', 'manual', null, $custo, 'Estoque inicial');
        }

        return redirect()->route('produtos.show', $p)->with('ok', $p->nome.' cadastrado.');
    }

    public function show(Produto $produto)
    {
        $this->authorize('estoque.ver');

        $precos = \App\Models\Auditoria::with('usuario')->where('empresa_id', \App\Support\Tenant::id())->where('modelo', 'Produto')->where('modelo_id', $produto->id)
            ->latest('id')->limit(100)->get()
            ->filter(fn ($a) => array_intersect(array_keys($a->depois ?? []), ['preco_centavos', 'custo_centavos']))->take(15);

        return view('produtos.show', ['produto' => $produto->load(['categoria', 'fabricante']), 'movimentos' => $produto->movimentos()->with('user')->limit(60)->get(), 'precos' => $precos]);
    }

    public function edit(Produto $produto)
    {
        $this->authorize('estoque.editar');

        return view('produtos.form', ['produto' => $produto] + $this->listas());
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

    /**
     * Mexer no saldo sempre com motivo: entrada sem nota, perda/uso interno, devoluções e contagem de inventário.
     * Grava usuário, data, saldo anterior, saldo novo e motivo.
     */
    public function movimentar(Request $request, Produto $produto)
    {
        $this->authorize('estoque.editar');
        $request->merge([
            'quantidade' => str_replace(',', '.', (string) $request->input('quantidade')),
            'custo' => $request->filled('custo') ? Dinheiro::paraCentavos($request->input('custo')) : null,
        ]);
        $dados = $request->validate([
            'tipo' => ['required', 'in:entrada,perda,devolucao_cliente,devolucao_fornecedor,contagem'],
            'quantidade' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'custo' => ['nullable', 'integer', 'min:0'],
            'observacao' => ['required', 'string', 'min:3', 'max:255'],
        ], ['observacao.required' => 'Escreva o motivo. Ele fica no histórico do produto.'], ['quantidade' => 'quantidade', 'observacao' => 'motivo']);
        $q = (float) $dados['quantidade'];
        $motivo = $dados['observacao'];

        if ($dados['tipo'] === 'contagem') {
            $dif = round($q - (float) $produto->estoque, 3);
            if ($dif == 0) {
                return back()->with('ok', 'A contagem bate com o sistema.');
            }
            $produto->movimentar($dif, 'ajuste', 'ajuste', null, null, 'Contagem: '.Produto::formatarQtd($q).' · '.$motivo);
        } else {
            if ($q <= 0) {
                return back()->withErrors(['quantidade' => 'Informe uma quantidade maior que zero.'])->withInput();
            }
            [$sinal, $tipo, $origem, $custo] = match ($dados['tipo']) {
                'entrada' => [1, 'entrada', 'manual', $dados['custo'] ?? $produto->custo_centavos],
                'devolucao_cliente' => [1, 'devolucao', 'devolucao_cliente', null],
                'devolucao_fornecedor' => [-1, 'devolucao', 'devolucao_fornecedor', null],
                default => [-1, 'saida', 'perda', null],
            };
            $produto->movimentar($sinal * $q, $tipo, $origem, null, $custo, $motivo);
        }

        return back()->with('ok', 'Estoque atualizado: '.$produto->estoqueFormatado().'.');
    }

    private function listas(): array
    {
        return [
            'categorias' => \App\Models\Categoria::where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
            'fabricantes' => \App\Models\Fabricante::where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
        ];
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
            'referencia_fabricante' => ['nullable', 'string', 'max:40'],
            'categoria_id' => ['nullable', Rule::exists('categorias', 'id')->where('empresa_id', Tenant::id())],
            'fabricante_id' => ['nullable', Rule::exists('fabricantes', 'id')->where('empresa_id', Tenant::id())],
            'unidade' => ['required', Rule::in(array_keys(Produto::UNIDADES))],
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
