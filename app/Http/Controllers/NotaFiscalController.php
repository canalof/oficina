<?php

namespace App\Http\Controllers;

use App\Models\NotaFiscal;
use App\Models\OrdemServico;
use App\Services\Fiscal\NotasFiscais;
use DomainException;
use Illuminate\Http\Request;

class NotaFiscalController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('notas.emitir');
        $status = $request->query('status');

        return view('notas.index', [
            'notas' => NotaFiscal::with('ordemServico.veiculo', 'ordemServico.cliente')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(40)->withQueryString(),
            'status' => $status,
            'demonstracao' => ! auth()->user()->empresa->fiscal_token,
        ]);
    }

    public function emitir(OrdemServico $os, string $tipo, NotasFiscais $notas)
    {
        $this->authorize('notas.emitir');
        try {
            $nota = $notas->emitir($os, $tipo);
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with($nota->status === 'erro' ? 'erro' : 'ok', match ($nota->status) {
            'autorizada' => $nota->tipoRotulo().' autorizada'.($nota->numero ? ', nº '.$nota->numero : '').'.',
            'erro' => 'O emissor recusou a nota: '.$nota->mensagem,
            default => 'Nota enviada. A prefeitura/SEFAZ ainda está processando; a situação atualiza sozinha em alguns minutos.',
        })->withFragment('notas');
    }

    public function atualizar(NotaFiscal $nota, NotasFiscais $notas)
    {
        $this->authorize('notas.emitir');
        $notas->atualizar($nota);

        return back()->with('ok', 'Situação: '.$nota->statusRotulo().'.')->withFragment('notas');
    }

    public function cancelar(Request $request, NotaFiscal $nota, NotasFiscais $notas)
    {
        $this->authorize('notas.emitir');
        try {
            $notas->cancelar($nota, trim((string) $request->input('justificativa')));
        } catch (DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with($nota->status === 'cancelada' ? 'ok' : 'erro', $nota->status === 'cancelada' ? 'Nota cancelada.' : 'Cancelamento não aceito: '.$nota->mensagem);
    }
}
