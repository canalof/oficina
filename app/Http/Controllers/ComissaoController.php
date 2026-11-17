<?php

namespace App\Http\Controllers;

use App\Models\Comissao;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ComissaoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('comissoes.ver');
        $mes = $this->mes($request);
        $lista = Comissao::with(['user', 'ordemServico.veiculo'])->whereDate('competencia', $mes->toDateString())->orderBy('user_id')->get();

        return view('financeiro.comissoes', [
            'mes' => $mes,
            'porPessoa' => $lista->groupBy('user_id'),
        ]);
    }

    public function pagar(Request $request)
    {
        $this->authorize('financeiro.editar');
        // O pagamento nunca deve cair silenciosamente no mês corrente se o
        // formulário chegar com uma competência ausente ou inválida.
        $dados = $request->validate([
            'mes' => ['required', 'regex:/^(?!0000)[0-9]{4}-(?:0[1-9]|1[0-2])$/D'],
            'user_id' => ['required', 'integer', 'min:1'],
        ]);
        $mes = Carbon::createFromFormat('!Y-m-d', $dados['mes'].'-01')->startOfDay();
        $n = Comissao::whereDate('competencia', $mes->toDateString())->where('user_id', (int) $dados['user_id'])->whereNull('paga_em')->update(['paga_em' => now()]);

        return back()->with('ok', $n ? 'Comissões marcadas como pagas.' : 'Nada a pagar.');
    }

    private function mes(Request $request): Carbon
    {
        $m = (string) $request->input('mes', now()->format('Y-m'));

        return preg_match('/^(?!0000)\d{4}-(?:0[1-9]|1[0-2])$/D', $m) ? Carbon::createFromFormat('Y-m-d', $m.'-01')->startOfDay() : now()->startOfMonth();
    }
}
