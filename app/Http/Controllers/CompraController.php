<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Produto;
use App\Services\Estoque\EntradaCompra;
use App\Support\Dinheiro;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class CompraController extends Controller
{
    public function index()
    {
        $this->authorize('estoque.ver');

        return view('compras.index', ['compras' => Compra::with('fornecedor')->withCount('itens')->latest('id')->paginate(30)]);
    }

    /** Recebe o XML da nota de compra. */
    public function store(Request $request, EntradaCompra $entrada)
    {
        $this->authorize('compras.lancar');
        $request->validate(['xml' => ['required', 'file', 'max:5120']], ['xml.required' => 'Escolha o arquivo XML da nota.']);

        try {
            $compra = $entrada->importar((string) file_get_contents($request->file('xml')->getRealPath()));
        } catch (InvalidArgumentException|DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return redirect()->route('compras.show', $compra)->with('ok', 'Nota lida. Confira os produtos e clique em "Dar entrada no estoque".');
    }

    public function show(Compra $compra)
    {
        $this->authorize('estoque.ver');
        $compra->load(['itens.produto', 'fornecedor']);

        return view('compras.show', ['compra' => $compra, 'produtos' => Produto::orderBy('nome')->get(['id', 'nome', 'codigo', 'unidade'])]);
    }

    public function lancar(Request $request, Compra $compra, EntradaCompra $entrada)
    {
        $this->authorize('compras.lancar');
        $ligacoes = array_map('strval', (array) $request->input('produto', []));
        $precos = array_map(fn ($v) => Dinheiro::paraCentavos($v), array_filter((array) $request->input('preco', []), fn ($v) => trim((string) $v) !== ''));
        $request->validate(['vencimento' => ['nullable', 'date']]);

        try {
            $entrada->lancar($compra, $ligacoes, $precos, $request->input('vencimento'));
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return redirect()->route('compras.show', $compra)->with('ok', 'Entrada feita. Estoque e contas a pagar atualizados.');
    }

    public function destroy(Compra $compra)
    {
        $this->authorize('compras.lancar');
        if ($compra->lancada()) {
            return back()->with('erro', 'Nota já lançada não pode ser excluída.');
        }
        if ($compra->xml_caminho) {
            Storage::disk('local')->delete($compra->xml_caminho);
        }
        $compra->delete();

        return redirect()->route('compras.index')->with('ok', 'Importação descartada.');
    }

    public function xml(Compra $compra)
    {
        $this->authorize('estoque.ver');
        abort_unless($compra->xml_caminho && Storage::disk('local')->exists($compra->xml_caminho), 404);

        return Storage::disk('local')->download($compra->xml_caminho, 'NF-'.$compra->numero.'.xml');
    }
}
