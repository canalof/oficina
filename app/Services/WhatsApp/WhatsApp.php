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
        return config('whatsapp.url') && config('whatsapp.chave') && $empresa->whatsapp_instancia;
    }

    public static function numero(?string $telefone): ?string
    {
        $n = preg_replace('/\D/', '', (string) $telefone);
        if (strlen($n) < 10) {
            return null;
        }

        return strlen($n) <= 11 ? '55'.$n : $n;
    }

    public function enviar(Empresa $empresa, ?string $telefone, string $texto): bool
    {
        $numero = self::numero($telefone);
        if (! $numero || ! $this->disponivel($empresa)) {
            return false;
        }
        try {
            $r = Http::timeout(15)
                ->withHeaders(['apikey' => config('whatsapp.chave')])
                ->post(rtrim(config('whatsapp.url'), '/').'/message/sendText/'.rawurlencode($empresa->whatsapp_instancia), [
                    'number' => $numero,
                    'text' => $texto,
                ]);

            if (! $r->successful()) {
                Log::warning('WhatsApp não enviado', ['empresa' => $empresa->id, 'status' => $r->status(), 'corpo' => mb_substr($r->body(), 0, 300)]);
            }

            return $r->successful();
        } catch (\Throwable $e) {
            Log::warning('WhatsApp indisponível: '.$e->getMessage());

            return false;
        }
    }
}
