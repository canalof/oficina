<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Support\Documento;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('clientes.ver');
        $busca = $request->query('busca');

        return view('clientes.index', [
            'clientes' => Cliente::busca($busca)->withCount('veiculos')->orderBy('nome')->paginate(20)->withQueryString(),
            'busca' => $busca,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('clientes.editar');

        return view('clientes.form', [
            'cliente' => new Cliente(['tipo_pessoa' => 'PF', 'aceita_whatsapp' => true, 'uf' => 'SP']),
            'voltarPlaca' => $request->query('placa'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('clientes.editar');
        $dados = $this->validar($request);
        if (! $request->boolean('confirmar_duplicado') && ($parecidos = \App\Support\Duplicados::clientes($dados))->isNotEmpty()) {
            return back()->withInput()->with('duplicados', $parecidos->map(fn ($c) => ['nome' => $c->nome, 'url' => route('clientes.show', $c), 'info' => $c->whatsapp ?: $c->telefone])->all());
        }
        $cliente = Cliente::create($dados);

        if ($placa = $request->input('voltar_placa')) {
            return redirect()->route('veiculos.create', ['placa' => $placa, 'cliente_id' => $cliente->id])
                ->with('ok', 'Cliente cadastrado. Agora confira os dados do veículo.');
        }

        return redirect()->route('clientes.show', $cliente)->with('ok', 'Cliente cadastrado.');
    }

    public function show(Cliente $cliente)
    {
        $this->authorize('clientes.ver');
        $cliente->load('veiculos');

        return view('clientes.show', ['cliente' => $cliente]);
    }

    public function edit(Cliente $cliente)
    {
        $this->authorize('clientes.editar');

        return view('clientes.form', ['cliente' => $cliente, 'voltarPlaca' => null]);
    }

    public function update(Request $request, Cliente $cliente)
    {
        $this->authorize('clientes.editar');
        $cliente->update($this->validar($request, $cliente));

        return redirect()->route('clientes.show', $cliente)->with('ok', 'Cliente atualizado.');
    }

    public function destroy(Cliente $cliente)
    {
        $this->authorize('clientes.excluir');
        if ($cliente->veiculos()->exists()) {
            return back()->with('erro', 'Este cliente tem veículos. Transfira ou exclua os veículos antes.');
        }
        $cliente->delete();

        return redirect()->route('clientes.index')->with('ok', 'Cliente excluído.');
    }

    private function validar(Request $request, ?Cliente $cliente = null): array
    {
        $request->merge([
            'cpf_cnpj' => Documento::normalizar($request->input('cpf_cnpj')) ?: null,
            'cep' => preg_replace('/\D/', '', (string) $request->input('cep')) ?: null,
            'uf' => strtoupper((string) $request->input('uf')) ?: null,
            'email' => strtolower(trim((string) $request->input('email'))) ?: null,
        ]);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'cpf_cnpj' => [
                'nullable', 'string',
                function ($attr, $valor, $fail) {
                    if ($valor && ! Documento::valido($valor)) {
                        $fail('CPF ou CNPJ inválido. Confira os números.');
                    }
                },
                Rule::unique('clientes', 'cpf_cnpj')
                    ->where('empresa_id', \App\Support\Tenant::id())
                    ->whereNull('deleted_at')
                    ->ignore($cliente?->id),
            ],
            'ie' => ['nullable', 'string', 'max:20'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'aceita_whatsapp' => ['boolean'],
            'email' => ['nullable', 'email', 'max:255'],
            'cep' => ['nullable', 'digits:8'],
            'logradouro' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:80'],
            'bairro' => ['nullable', 'string', 'max:80'],
            'cidade' => ['nullable', 'string', 'max:80'],
            'uf' => ['nullable', 'string', 'size:2'],
            'nascimento' => ['nullable', 'date', 'before:today'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ], ['cpf_cnpj.unique' => 'Já existe um cliente com este CPF/CNPJ.'], ['cpf_cnpj' => 'CPF/CNPJ']);

        $dados['tipo_pessoa'] = Documento::tipo($dados['cpf_cnpj'] ?? null) ?? ($request->input('tipo_pessoa') === 'PJ' ? 'PJ' : 'PF');
        $dados['aceita_whatsapp'] = $request->boolean('aceita_whatsapp');

        return $dados;
    }
}
