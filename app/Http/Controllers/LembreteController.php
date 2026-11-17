<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Lembrete;
use App\Services\WhatsApp\WhatsApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    /**
     * O envio automático é confirmado pela API. Abrir o WhatsApp manualmente
     * NÃO prova que a mensagem foi enviada pelo operador.
     */
    public function enviar(Lembrete $lembrete, WhatsApp $whatsapp)
    {
        $this->authorize('agenda.editar');
        $empresa = Empresa::findOrFail($lembrete->empresa_id);
        $atual = Lembrete::whereKey($lembrete->id)->firstOrFail();
        abort_unless($atual->status === 'pendente', 409, 'Lembrete não está pendente.');
        $this->validarDestinatario($atual);
        $texto = $atual->mensagem($empresa->nome_fantasia);
        $fone = $atual->cliente->whatsapp ?: $atual->cliente->telefone;

        if ($whatsapp->disponivel($empresa)) {
            // Evita a duplicação sequencial por envio repetido da mesma tela.
            // A confirmação aqui é o sucesso retornado pelo provedor.
            if (! $whatsapp->enviar($empresa, $fone, $texto, 'lembrete', $atual->id)) {
                return back()->with('erro', 'Não foi possível enviar pelo WhatsApp automático. Confira o número do cliente e a instância.');
            }
            Lembrete::whereKey($atual->id)->where('status', 'pendente')
                ->update(['status' => 'enviado', 'enviado_em' => now()]);

            return back()->with('ok', 'Lembrete enviado pela integração.');
        }

        $numero = WhatsApp::numero($fone);
        if (! $numero) {
            return back()->with('erro', 'Cliente sem WhatsApp cadastrado.');
        }

        // Não marcar como enviado: a pessoa ainda pode fechar o WhatsApp.
        return redirect()->away('https://wa.me/'.$numero.'?text='.rawurlencode($texto));
    }

    /** O operador confirma o envio apenas após fazê-lo no WhatsApp. */
    public function confirmarEnvioManual(Lembrete $lembrete, WhatsApp $whatsapp)
    {
        $this->authorize('agenda.editar');
        DB::transaction(function () use ($lembrete, $whatsapp) {
            $atual = Lembrete::whereKey($lembrete->id)->lockForUpdate()->firstOrFail();
            abort_unless($atual->status === 'pendente', 409, 'Lembrete não está pendente.');
            $this->validarDestinatario($atual);
            $empresa = Empresa::findOrFail($atual->empresa_id);
            abort_if($whatsapp->disponivel($empresa), 409, 'O envio automático deve ser confirmado pela integração.');
            abort_unless(WhatsApp::numero($atual->cliente->whatsapp ?: $atual->cliente->telefone), 422, 'Cliente sem WhatsApp cadastrado.');
            $atual->update(['status' => 'enviado', 'enviado_em' => now()]);
        });

        return back()->with('ok', 'Envio manual confirmado.');
    }

    private function validarDestinatario(Lembrete $lembrete): void
    {
        $cliente = $lembrete->cliente;
        $veiculo = $lembrete->veiculo;
        if (! $cliente || $cliente->trashed() || ! $cliente->aceita_whatsapp || ! $veiculo || $veiculo->trashed()
            || ($lembrete->inspecao_id && $veiculo->cliente_id !== $cliente->id)) {
            throw ValidationException::withMessages([
                'lembrete' => 'Lembrete não pode ser enviado: cliente sem autorização ou cadastro/veículo excluído.',
            ]);
        }
    }

    public function descartar(Lembrete $lembrete)
    {
        $this->authorize('agenda.editar');
        $lembrete->update(['status' => 'descartado']);

        return back()->with('ok', 'Lembrete descartado.');
    }
}
