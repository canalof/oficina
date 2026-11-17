<?php

namespace App\Http\Controllers;

use App\Models\ContaPagar;
use App\Models\Fornecedor;
use App\Services\Financeiro\Financeiro;
use App\Support\Dinheiro;
use App\Support\Tenant;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ContaPagarController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('financeiro.ver');
        $filtro = $request->query('filtro', 'abertas');
        $q = ContaPagar::with('fornecedor');
        match ($filtro) {
            'vencidas' => $q->where('status', 'aberta')->where('vencimento', '<', today()),
            'pagas' => $q->where('status', 'paga')->orderByDesc('pago_em'),
            default => $q->where('status', 'aberta'),
        };

        return view('financeiro.pagar', [
            'contas' => $q->orderBy('vencimento')->paginate(40)->withQueryString(),
            'filtro' => $filtro,
            'totalAberto' => (int) ContaPagar::where('status', 'aberta')->sum('valor_centavos'),
            'totalSemana' => (int) ContaPagar::where('status', 'aberta')->whereBetween('vencimento', [today(), today()->addDays(7)])->sum('valor_centavos'),
            'fornecedores' => Fornecedor::orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('financeiro.editar');
        $request->merge(['valor_centavos' => Dinheiro::paraCentavos($request->input('valor'))]);
        $dados = $request->validate([
            'fornecedor_id' => ['nullable', Rule::exists('fornecedores', 'id')->where('empresa_id', Tenant::id())->whereNull('deleted_at')],
            'categoria' => ['required', Rule::in(ContaPagar::CATEGORIAS)],
            'descricao' => ['required', 'string', 'max:200'],
            'documento' => ['nullable', 'string', 'max:40'],
            'valor_centavos' => ['integer', 'min:1'],
            'vencimento' => ['required', 'date'],
            'repetir' => ['nullable', 'integer', 'min:1', 'max:24'],
        ], ['valor_centavos.min' => 'Informe o valor.'], ['repetir' => 'repetições']);

        $vezes = (int) ($dados['repetir'] ?? 1);
        unset($dados['repetir']);
        $venc = Carbon::parse($dados['vencimento']);
        // Todas as parcelas precisam ser gravadas juntas: sem lançamento parcial.
        DB::transaction(function () use ($vezes, $dados, $venc) {
            for ($i = 0; $i < $vezes; $i++) {
                ContaPagar::create(array_merge($dados, [
                    'vencimento' => $venc->copy()->addMonthsNoOverflow($i),
                    'descricao' => $dados['descricao'].($vezes > 1 ? ' ('.($i + 1).'/'.$vezes.')' : ''),
                ]));
            }
        }, 3);

        return back()->with('ok', $vezes > 1 ? "{$vezes} contas lançadas." : 'Conta lançada.');
    }

    public function pagar(Request $request, ContaPagar $conta, Financeiro $financeiro)
    {
        $this->authorize('financeiro.editar');
        $forma = (string) $request->input('forma');
        abort_unless(in_array($forma, Financeiro::IMEDIATAS, true) || $forma === 'boleto', 422);
        try {
            $financeiro->pagarConta($conta, $forma);
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('ok', 'Pagamento registrado.');
    }

    public function cancelar(ContaPagar $conta)
    {
        $this->authorize('financeiro.editar');
        // O model recebido por route binding pode ter ficado desatualizado
        // enquanto outra requisição quitava este mesmo título.
        $cancelada = DB::transaction(function () use ($conta) {
            $atual = ContaPagar::query()->whereKey($conta->getKey())->lockForUpdate()->firstOrFail();
            if ($atual->status !== 'aberta') {
                return false;
            }
            $atual->update(['status' => 'cancelada']);
            return true;
        }, 3);
        abort_unless($cancelada, 422, 'Somente contas em aberto podem ser canceladas.');

        return back()->with('ok', 'Conta cancelada.');
    }
}
