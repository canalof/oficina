<?php

namespace App\Http\Controllers;

use App\Models\Comissao;
use Carbon\Carbon;
use Illuminate\Http\Request;

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
        $mes = $this->mes($request);
        $n = Comissao::whereDate('competencia', $mes->toDateString())->where('user_id', (int) $request->input('user_id'))->whereNull('paga_em')->update(['paga_em' => now()]);

        return back()->with('ok', $n ? 'Comissões marcadas como pagas.' : 'Nada a pagar.');
    }

    private function mes(Request $request): Carbon
    {
        $m = (string) $request->input('mes', now()->format('Y-m'));

        return preg_match('/^\d{4}-\d{2}$/', $m) ? Carbon::createFromFormat('Y-m-d', $m.'-01')->startOfDay() : now()->startOfMonth();
    }
}
