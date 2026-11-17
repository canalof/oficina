<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\ContaReceber;
use App\Services\Financeiro\Financeiro;
use App\Support\Dinheiro;
use App\Support\Tenant;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ContaReceberController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('financeiro.ver');
        $filtro = $request->query('filtro', 'abertas');

        $q = ContaReceber::with(['cliente', 'ordemServico']);
        match ($filtro) {
            'vencidas' => $q->where('status', 'aberta')->where('vencimento', '<', today()),
            'recebidas' => $q->whereIn('status', ['recebida', 'estornada'])->orderByDesc('recebido_em'),
            default => $q->where('status', 'aberta'),
        };

        return view('financeiro.receber', [
            'contas' => $q->orderBy('vencimento')->paginate(40)->withQueryString(),
            'filtro' => $filtro,
            'totalAberto' => (int) ContaReceber::where('status', 'aberta')->sum('valor_centavos'),
            'totalVencido' => (int) ContaReceber::where('status', 'aberta')->where('vencimento', '<', today())->sum('valor_centavos'),
            'clientes' => Cliente::orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('financeiro.editar');
        $request->merge(['valor_centavos' => Dinheiro::paraCentavos($request->input('valor'))]);
        $dados = $request->validate([
            'cliente_id' => ['nullable', Rule::exists('clientes', 'id')->where('empresa_id', Tenant::id())->whereNull('deleted_at')],
            'descricao' => ['required', 'string', 'max:200'],
            'valor_centavos' => ['integer', 'min:1'],
            'vencimento' => ['required', 'date'],
        ], ['valor_centavos.min' => 'Informe o valor.']);
        ContaReceber::create($dados + ['forma' => 'prazo']);

        return back()->with('ok', 'Conta a receber lançada.');
    }

    public function receber(Request $request, ContaReceber $conta, Financeiro $financeiro)
    {
        $this->authorize('caixa.operar');
        $forma = (string) $request->input('forma');
        abort_unless(isset(Financeiro::FORMAS[$forma]) && in_array($forma, Financeiro::IMEDIATAS, true), 422);
        try {
            $financeiro->receberConta($conta, $forma);
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('ok', Dinheiro::formatar($conta->valor_centavos).' recebido.');
    }

    public function estornar(Request $request, ContaReceber $conta, Financeiro $financeiro)
    {
        $this->authorize('financeiro.editar');
        try {
            $financeiro->estornarRecebimento($conta, (string) $request->input('motivo'));
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('ok', 'Estorno registrado.');
    }

    public function cancelar(ContaReceber $conta)
    {
        $this->authorize('financeiro.editar');
        // Revalidar sob bloqueio para não cancelar um recebimento concluído
        // por outra requisição depois do route binding.
        $cancelada = DB::transaction(function () use ($conta) {
            $atual = ContaReceber::query()->whereKey($conta->getKey())->lockForUpdate()->firstOrFail();
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
