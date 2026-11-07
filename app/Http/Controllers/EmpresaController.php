<?php

namespace App\Http\Controllers;

use App\Support\Documento;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmpresaController extends Controller
{
    public function edit(Request $request)
    {
        return view('empresa.edit', ['empresa' => $request->user()->empresa]);
    }

    public function update(Request $request)
    {
        $empresa = $request->user()->empresa;
        $request->merge(['cnpj' => Documento::normalizar($request->input('cnpj')) ?: null, 'uf' => strtoupper((string) $request->input('uf')) ?: null]);

        $dados = $request->validate([
            'nome_fantasia' => ['required', 'string', 'max:255'],
            'razao_social' => ['nullable', 'string', 'max:255'],
            'cnpj' => ['nullable', 'size:14', function ($a, $v, $fail) {
                if ($v && ! Documento::cnpjValido($v)) {
                    $fail('CNPJ inválido.');
                }
            }, Rule::unique('empresas', 'cnpj')->ignore($empresa->id)],
            'telefone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
            'cidade' => ['nullable', 'string', 'max:80'],
            'uf' => ['nullable', 'size:2'],
        ], [], ['nome_fantasia' => 'nome fantasia', 'razao_social' => 'razão social']);

        $empresa->update($dados);

        return back()->with('ok', 'Dados da empresa salvos.');
    }
}
