<?php

namespace App\Http\Controllers;

use App\Models\Servico;
use App\Support\Dinheiro;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Catálogo de serviços com preço padrão (agiliza o orçamento). */
class ServicoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('os.ver');
        $busca = trim((string) $request->query('busca'));

        return view('servicos.index', [
            'servicos' => Servico::when($busca, fn ($q) => $q->where('nome', 'like', '%'.$busca.'%'))->orderBy('nome')->paginate(50)->withQueryString(),
            'busca' => $busca,
            'editando' => $request->query('editar') ? Servico::find($request->query('editar')) : null,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('servicos.gerenciar');
        $s = Servico::create($this->validar($request));

        return redirect()->route('servicos.index')->with('ok', $s->nome.' adicionado ao catálogo.');
    }

    public function update(Request $request, Servico $servico)
    {
        $this->authorize('servicos.gerenciar');
        $servico->update($this->validar($request, $servico));

        return redirect()->route('servicos.index')->with('ok', 'Serviço atualizado.');
    }

    public function destroy(Servico $servico)
    {
        $this->authorize('servicos.gerenciar');
        $servico->delete();

        return redirect()->route('servicos.index')->with('ok', $servico->nome.' removido do catálogo. As OS antigas continuam com ele.');
    }

    private function validar(Request $request, ?Servico $servico = null): array
    {
        $request->merge(['preco_centavos' => Dinheiro::paraCentavos($request->input('preco'))]);
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120', Rule::unique('servicos', 'nome')->where('empresa_id', Tenant::id())->whereNull('deleted_at')->ignore($servico?->id)],
            'preco_centavos' => ['required', 'integer', 'min:0', 'max:99999999'],
            'tempo_minutos' => ['nullable', 'integer', 'min:1', 'max:6000'],
            'garantia_dias' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ], ['nome.unique' => 'Já existe um serviço com esse nome.'], ['tempo_minutos' => 'tempo', 'garantia_dias' => 'garantia']);
        $dados['ativo'] = $request->boolean('ativo', true);

        return $dados;
    }
}
