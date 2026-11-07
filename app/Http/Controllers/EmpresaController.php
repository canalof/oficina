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
        $request->merge([
            'cnpj' => Documento::normalizar($request->input('cnpj')) ?: null,
            'uf' => strtoupper((string) $request->input('uf')) ?: null,
            'cep' => preg_replace('/\D/', '', (string) $request->input('cep')) ?: null,
            'aliquota_iss' => str_replace(',', '.', (string) $request->input('aliquota_iss')) ?: 0,
        ]);

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
            'cep' => ['nullable', 'digits:8'],
            'logradouro' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'bairro' => ['nullable', 'string', 'max:80'],
            'inscricao_estadual' => ['nullable', 'string', 'max:20'],
            'inscricao_municipal' => ['nullable', 'string', 'max:20'],
            'codigo_municipio' => ['nullable', 'digits:7'],
            'regime_tributario' => ['required', 'in:1,2,3'],
            'cfop_padrao' => ['required', 'digits:4'],
            'csosn_padrao' => ['required', 'digits:3'],
            'item_lista_servico' => ['required', 'string', 'max:10'],
            'aliquota_iss' => ['required', 'numeric', 'min:0', 'max:10'],
            'fiscal_ambiente' => ['required', 'in:homologacao,producao'],
            'whatsapp_instancia' => ['nullable', 'string', 'max:80'],
            'agenda_capacidade' => ['required', 'integer', 'min:1', 'max:50'],
            'orcamento_validade_dias' => ['required', 'integer', 'min:1', 'max:90'],
        ], [], ['nome_fantasia' => 'nome fantasia', 'razao_social' => 'razão social', 'codigo_municipio' => 'código do município', 'aliquota_iss' => 'alíquota do ISS']);

        // O token só é trocado quando algo novo é digitado (nunca volta para a tela)
        if ($request->filled('fiscal_token')) {
            $dados['fiscal_token'] = trim($request->input('fiscal_token'));
        }
        if ($request->boolean('remover_token')) {
            $dados['fiscal_token'] = null;
        }
        $dados['estoque_negativo'] = $request->boolean('estoque_negativo');
        $empresa->update($dados);

        return back()->with('ok', 'Dados da empresa salvos.');
    }
}
