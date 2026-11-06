<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Lembrete;
use App\Services\WhatsApp\WhatsApp;
use Illuminate\Http\Request;

class LembreteController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('agenda.ver');
        $ver = $request->query('ver', 'proximos');
        $q = Lembrete::with(['veiculo', 'cliente']);
        match ($ver) {
            'enviados' => $q->where('status', 'enviado')->orderByDesc('enviado_em'),
            'todos' => $q->whereIn('status', ['pendente', 'enviado'])->orderBy('data_prevista'),
            default => $q->where('status', 'pendente')->where('data_prevista', '<=', today()->addDays(30))->orderBy('data_prevista'),
        };

        return view('lembretes.index', [
            'lembretes' => $q->paginate(40)->withQueryString(),
            'ver' => $ver,
            'automatico' => app(WhatsApp::class)->disponivel(auth()->user()->empresa),
        ]);
    }

    /** Envia pela Evolution API ou abre o WhatsApp com a mensagem pronta. */
    public function enviar(Lembrete $lembrete, WhatsApp $whatsapp)
    {
        $this->authorize('agenda.editar');
        $empresa = Empresa::find($lembrete->empresa_id);
        $texto = $lembrete->mensagem($empresa->nome_fantasia);
        $fone = $lembrete->cliente?->whatsapp ?: $lembrete->cliente?->telefone;

        if ($whatsapp->disponivel($empresa)) {
            if (! $whatsapp->enviar($empresa, $fone, $texto)) {
                return back()->with('erro', 'Não foi possível enviar pelo WhatsApp automático. Confira o número do cliente e a instância.');
            }
            $lembrete->update(['status' => 'enviado', 'enviado_em' => now()]);

            return back()->with('ok', 'Lembrete enviado.');
        }

        $numero = WhatsApp::numero($fone);
        if (! $numero) {
            return back()->with('erro', 'Cliente sem WhatsApp cadastrado.');
        }
        $lembrete->update(['status' => 'enviado', 'enviado_em' => now()]);

        return redirect()->away('https://wa.me/'.$numero.'?text='.rawurlencode($texto));
    }

    public function descartar(Lembrete $lembrete)
    {
        $this->authorize('agenda.editar');
        $lembrete->update(['status' => 'descartado']);

        return back()->with('ok', 'Lembrete descartado.');
    }
}
