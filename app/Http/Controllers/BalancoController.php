<?php

namespace App\Http\Controllers;

use App\Models\Balanco;
use App\Models\Categoria;
use App\Models\Produto;
use App\Services\Estoque\AplicarBalanco;
use App\Support\CsvSeguro;
use App\Support\ImportacaoNumeros;
use App\Support\PlanilhaCsv;
use Illuminate\Http\Request;

/**
 * Balanço de estoque: contar vários produtos de uma vez (na tela, com leitor de código
 * de barras, ou por planilha) e ajustar só o que estiver diferente.
 */
class BalancoController extends Controller
{
    /** Máximo de produtos numa tela/planilha (o formulário inteiro vai num POST). */
    public const LIMITE = 800;

    public function index()
    {
        $this->authorize('estoque.ver');

        return view('balancos.index', ['balancos' => Balanco::with('user')->latest('id')->paginate(30)]);
    }

    public function create(Request $request)
    {
        $this->authorize('estoque.editar');
        if ($request->boolean('descartar')) {
            session()->forget('balanco_planilha');

            return redirect()->route('balancos.create');
        }
        $planilha = session('balanco_planilha'); // [produto_id => contado] vindo da planilha, até aplicar ou descartar

        $produtos = $planilha
            ? Produto::whereIn('id', array_keys($planilha))->orderBy('nome')->get()
            : $this->filtrados($request)->limit(self::LIMITE + 1)->get();
        $cortou = ! $planilha && $produtos->count() > self::LIMITE;

        return view('balancos.create', [
            'produtos' => $produtos->take(self::LIMITE),
            'cortou' => $cortou,
            'planilha' => $planilha,
            'categorias' => Categoria::where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
            'locais' => Produto::where('ativo', true)->whereNotNull('localizacao')->where('localizacao', '<>', '')->distinct()->orderBy('localizacao')->pluck('localizacao'),
            'filtros' => $request->only(['busca', 'categoria', 'local']),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('estoque.editar');
        $request->validate([
            'contado' => ['required', 'array', 'max:'.self::LIMITE],
            'visto' => ['array'],
            'observacao' => ['nullable', 'string', 'max:200'],
        ], ['contado.required' => 'Nenhum produto na lista.']);

        $contagens = [];
        $erros = [];
        foreach ($request->input('contado') as $id => $valor) {
            if (! ctype_digit((string) $id)) {
                continue;
            }
            try {
                $q = ImportacaoNumeros::quantidade($valor, 'Quantidade contada');
            } catch (\InvalidArgumentException $e) {
                $erros["contado.$id"] = $e->getMessage();

                continue;
            }
            if ($q !== null) {
                $visto = $request->input("visto.$id");
                $contagens[(int) $id] = ['contado' => $q, 'visto' => is_numeric($visto) ? (float) $visto : null];
            }
        }
        if ($erros) {
            return back()->withErrors($erros)->withInput();
        }
        if ($contagens === []) {
            return back()->withErrors(['contado' => 'Preencha a quantidade contada de pelo menos um produto. Os que ficarem em branco não mudam.'])->withInput();
        }
        // Só produtos desta oficina (o escopo da empresa filtra os ids de fora)
        $validos = Produto::whereIn('id', array_keys($contagens))->pluck('id')->all();
        if (count($validos) !== count($contagens)) {
            return back()->withErrors(['contado' => 'A lista tem produto que não é desta oficina ou foi excluído. Abra o balanço de novo.'])->withInput();
        }

        try {
            $balanco = AplicarBalanco::executar($contagens, $request->input('observacao'), $request->user()->id);
        } catch (\DomainException $e) {
            return back()->withErrors(['contado' => $e->getMessage()])->withInput();
        }
        session()->forget('balanco_planilha');

        return redirect()->route('balancos.show', $balanco)
            ->with('ok', $balanco->produtos_ajustados
                ? "Balanço aplicado: {$balanco->produtos_ajustados} de {$balanco->produtos_contados} produtos ajustados."
                : 'Balanço registrado: a contagem bateu com o sistema em todos os produtos.');
    }

    public function show(Balanco $balanco)
    {
        $this->authorize('estoque.ver');
        $itens = $balanco->itens()->with('produto')->get()
            ->sortBy([fn ($a, $b) => ((float) $a->diferenca == 0) <=> ((float) $b->diferenca == 0), fn ($a, $b) => strcmp((string) $a->produto?->nome, (string) $b->produto?->nome)]);

        return view('balancos.show', ['balanco' => $balanco->load('user'), 'itens' => $itens]);
    }

    /** Planilha para contar no papel ou no Excel: preencha a coluna "contado" e envie de volta. */
    public function planilha(Request $request)
    {
        $this->authorize('estoque.editar');
        $produtos = $this->filtrados($request)->limit(self::LIMITE)->get();
        $nome = 'torque-balanco-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($produtos) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, ['id', 'codigo', 'codigo_barras', 'nome', 'local', 'unidade', 'estoque_sistema', 'contado'], ';', '"', '');
            foreach ($produtos as $p) {
                fputcsv($f, array_map(CsvSeguro::celula(...), [$p->id, $p->codigo, $p->codigo_barras, $p->nome, $p->localizacao, $p->unidade, rtrim(rtrim(number_format((float) $p->estoque, 3, ',', ''), '0'), ','), '']), ';', '"', '');
            }
            fclose($f);
        }, $nome, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Recebe a planilha preenchida e abre a tela de conferência com as quantidades (nada é aplicado ainda). */
    public function receberPlanilha(Request $request)
    {
        $this->authorize('estoque.editar');
        $request->validate(['arquivo' => ['required', 'file', 'max:5120']], ['arquivo.required' => 'Escolha a planilha (.csv) preenchida.']);

        $linhas = PlanilhaCsv::ler($request->file('arquivo')->getRealPath());
        $cab = array_map(fn ($c) => strtolower(trim(\Illuminate\Support\Str::ascii($c))), array_shift($linhas) ?? []);
        $col = array_flip($cab);
        if (! isset($col['contado']) || (! isset($col['id']) && ! isset($col['codigo']) && ! isset($col['codigo_barras']))) {
            return back()->with('erro', 'Use a planilha baixada no balanço: ela precisa das colunas "id" (ou "codigo") e "contado".');
        }

        $contagens = [];
        $erros = [];
        foreach ($linhas as $n => $l) {
            $linha = $n + 2;
            $pega = fn (string $c) => isset($col[$c]) ? trim((string) ($l[$col[$c]] ?? '')) : '';
            try {
                $q = ImportacaoNumeros::quantidade($pega('contado'), 'Contado');
            } catch (\InvalidArgumentException $e) {
                $erros[] = "Linha {$linha}: ".$e->getMessage();

                continue;
            }
            if ($q === null) {
                continue; // em branco = não contou
            }
            $produto = null;
            if (ctype_digit($pega('id'))) {
                $produto = Produto::find((int) $pega('id'));
            }
            if (! $produto && $pega('codigo') !== '') {
                $produto = Produto::where('codigo', $pega('codigo'))->first();
            }
            if (! $produto && $pega('codigo_barras') !== '') {
                $produto = Produto::where('codigo_barras', preg_replace('/\D/', '', $pega('codigo_barras')))->first();
            }
            if (! $produto) {
                $erros[] = "Linha {$linha}: produto não encontrado nesta oficina.";

                continue;
            }
            $contagens[$produto->id] = $q;
        }

        if (count($contagens) > self::LIMITE) {
            return back()->with('erro', 'A planilha tem mais de '.self::LIMITE.' produtos contados. Divida em partes (por categoria ou local).');
        }
        if ($erros) {
            return back()->with('erro', 'Corrija a planilha e envie de novo.')->with('erros_planilha', array_slice($erros, 0, 30));
        }
        if ($contagens === []) {
            return back()->with('erro', 'A coluna "contado" está vazia em todas as linhas.');
        }

        session()->put('balanco_planilha', $contagens);

        return redirect()->route('balancos.create');
    }

    private function filtrados(Request $request)
    {
        return Produto::where('ativo', true)->busca($request->query('busca'))
            ->when($request->query('categoria'), fn ($q, $c) => $q->where('categoria_id', $c))
            ->when($request->query('local'), fn ($q, $l) => $q->where('localizacao', $l))
            ->orderBy('localizacao')->orderBy('nome');
    }
}
