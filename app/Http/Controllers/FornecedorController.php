<?php

namespace App\Http\Controllers;

use App\Models\Fornecedor;
use App\Support\Documento;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FornecedorController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('estoque.ver');
        $busca = trim((string) $request->query('busca'));

        return view('fornecedores.index', [
            'fornecedores' => Fornecedor::when($busca, fn ($q) => $q->where('nome', 'like', "%{$busca}%"))->withCount('compras')->orderBy('nome')->paginate(40)->withQueryString(),
            'busca' => $busca,
        ]);
    }

    public function create()
    {
        $this->authorize('compras.lancar');

        return view('fornecedores.form', ['fornecedor' => new Fornecedor]);
    }

    public function store(Request $request)
    {
        $this->authorize('compras.lancar');
        $dados = $this->validar($request);
        if (! $request->boolean('confirmar_duplicado') && ($parecidos = \App\Support\Duplicados::fornecedores($dados))->isNotEmpty()) {
            return back()->withInput()->with('duplicados', $parecidos->map(fn ($f) => ['nome' => $f->nome, 'url' => route('fornecedores.edit', $f), 'info' => null])->all());
        }
        Fornecedor::create($dados);

        return redirect()->route('fornecedores.index')->with('ok', 'Fornecedor cadastrado.');
    }

    public function edit(Fornecedor $fornecedor)
    {
        $this->authorize('compras.lancar');

        return view('fornecedores.form', ['fornecedor' => $fornecedor]);
    }

    public function update(Request $request, Fornecedor $fornecedor)
    {
        $this->authorize('compras.lancar');
        $fornecedor->update($this->validar($request, $fornecedor));

        return redirect()->route('fornecedores.index')->with('ok', 'Fornecedor atualizado.');
    }

    private function validar(Request $request, ?Fornecedor $f = null): array
    {
        $request->merge(['cpf_cnpj' => Documento::normalizar($request->input('cpf_cnpj')) ?: null, 'uf' => strtoupper((string) $request->input('uf')) ?: null]);

        return $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'cpf_cnpj' => ['nullable', function ($a, $v, $fail) {
                if ($v && ! Documento::valido($v)) {
                    $fail('CPF ou CNPJ inválido.');
                }
            }, Rule::unique('fornecedores', 'cpf_cnpj')->where('empresa_id', Tenant::id())->whereNull('deleted_at')->ignore($f?->id)],
            'telefone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
            'contato' => ['nullable', 'string', 'max:80'],
            'cidade' => ['nullable', 'string', 'max:80'],
            'uf' => ['nullable', 'size:2'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ], ['cpf_cnpj.unique' => 'Este fornecedor já está cadastrado.'], ['cpf_cnpj' => 'CPF/CNPJ']);
    }
}
