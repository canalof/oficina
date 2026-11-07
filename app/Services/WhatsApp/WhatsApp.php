<?php

namespace App\Services\WhatsApp;

use App\Models\Empresa;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envio automático pelo WhatsApp usando a Evolution API (opcional).
 * Configure EVOLUTION_URL e EVOLUTION_KEY no .env e o nome da instância em Empresa.
 * Sem isso, o sistema usa os botões que abrem o WhatsApp com a mensagem pronta.
 */
class WhatsApp
{
    public function disponivel(Empresa $empresa): bool
    {
        return config('whatsapp.url') && config('whatsapp.chave') && $empresa->whatsapp_instancia
            && ($empresa->plano === null || $empresa->plano->whatsapp_automatico);
    }

    public static function numero(?string $telefone): ?string
    {
        $n = preg_replace('/\D/', '', (string) $telefone);
        if (strlen($n) < 10) {
            return null;
        }

        return strlen($n) <= 11 ? '55'.$n : $n;
    }

    /** Envia e registra a mensagem (mensagens_whatsapp), para conferência e novas tentativas. */
    public function enviar(Empresa $empresa, ?string $telefone, string $texto, string $tipo = 'outro', ?int $referencia = null): bool
    {
        $numero = self::numero($telefone);
        if (! $numero || ! $this->disponivel($empresa)) {
            return false;
        }
        $msg = \App\Models\MensagemWhatsapp::create([
            'empresa_id' => $empresa->id, 'destino' => $numero, 'tipo' => $tipo, 'referencia_id' => $referencia, 'texto' => $texto,
        ]);

        return $this->tentar($empresa, $msg);
    }

    public function tentar(Empresa $empresa, \App\Models\MensagemWhatsapp $msg): bool
    {
        $ok = $this->postar($empresa, $msg->destino, $msg->texto, $erro);
        $msg->update([
            'status' => $ok ? 'enviada' : 'falhou', 'tentativas' => $msg->tentativas + 1,
            'erro' => $ok ? null : mb_substr((string) $erro, 0, 255), 'enviada_em' => $ok ? now() : null,
        ]);

        return $ok;
    }

    private function postar(Empresa $empresa, string $numero, string $texto, ?string &$erro = null): bool
    {
        try {
            $r = Http::timeout(15)
                ->withHeaders(['apikey' => config('whatsapp.chave')])
                ->post(rtrim(config('whatsapp.url'), '/').'/message/sendText/'.rawurlencode($empresa->whatsapp_instancia), [
                    'number' => $numero,
                    'text' => $texto,
                ]);

            if (! $r->successful()) {
                $erro = 'HTTP '.$r->status();
                Log::warning('WhatsApp não enviado', ['empresa' => $empresa->id, 'status' => $r->status()]);
            }

            return $r->successful();
        } catch (\Throwable $e) {
            $erro = 'Sem conexão com a Evolution API';
            Log::warning('WhatsApp indisponível', ['empresa' => $empresa->id]);

            return false;
        }
    }
}
