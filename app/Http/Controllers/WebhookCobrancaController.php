<?php

namespace App\Http\Controllers;

use App\Services\Cobranca\Assinaturas;
use Illuminate\Http\Request;

/** Recebe os avisos do Asaas (pagamento recebido, vencido, estornado). */
class WebhookCobrancaController extends Controller
{
    public function asaas(Request $request, Assinaturas $assinaturas)
    {
        $esperado = (string) config('cobranca.asaas.webhook_token');
        $recebido = (string) $request->header('asaas-access-token');
        if ($esperado === '' || ! hash_equals($esperado, $recebido)) {
            abort(401);
        }
        $assinaturas->processarWebhook($request->json()->all());

        return response('', 200);
    }
}
