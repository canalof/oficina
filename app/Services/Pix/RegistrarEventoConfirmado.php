<?php

namespace App\Services\Pix;

use App\Models\CobrancaPix;
use App\Models\ContaReceber;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Serviço interno: receber SOMENTE eventos já autenticados e normalizados.
 * NÃO expor diretamente como rota HTTP: cada banco requer verificador próprio.
 * Não altera contas a receber nem movimentações bancárias: a baixa automática
 * fica bloqueada até integração bancária homologada e testes de conciliação.
 */
final class RegistrarEventoConfirmado
{
    public function registrar(EventoConfirmado $evento): string
    {
        if (!Tenant::definido()) {
            throw new RuntimeException('Empresa obrigatória para evento Pix.');
        }

        $impressao = hash('sha256', json_encode([
            $evento->provedor, $evento->eventoExterno, $evento->cobrancaExterna,
            $evento->txid, $evento->valorCentavos, $evento->tipo,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($evento, $impressao) {
            // Serializa o processamento dos eventos da mesma cobrança.
            $cobranca = CobrancaPix::query()
                ->where('provedor', $evento->provedor)
                ->where('id_externo', $evento->cobrancaExterna)
                ->lockForUpdate()->firstOrFail();

            $jaRegistrado = DB::table('pix_eventos')
                ->where('provedor', $evento->provedor)
                ->where('evento_externo', $evento->eventoExterno)
                ->first();
            if ($jaRegistrado) {
                if ((int) $jaRegistrado->cobranca_pix_id !== (int) $cobranca->id
                    || (int) $jaRegistrado->empresa_id !== Tenant::id()
                    || !hash_equals((string) $jaRegistrado->impressao_payload, $impressao)) {
                    throw new RuntimeException('Identificador de evento Pix em conflito.');
                }
                return 'duplicado';
            }

            if ((int) $cobranca->empresa_id !== Tenant::id()) {
                throw new RuntimeException('Cobrança pertence a outra empresa.');
            }
            if ($cobranca->txid !== $evento->txid || (int) $cobranca->valor_centavos !== $evento->valorCentavos) {
                throw new RuntimeException('TXID ou valor Pix divergente.');
            }
            if (!in_array($cobranca->status, ['pendente', 'confirmado'], true)) {
                throw new RuntimeException('Cobrança Pix não está ativa.');
            }
            if ($cobranca->conta_receber_id !== null) {
                $titulo = ContaReceber::query()->whereKey($cobranca->conta_receber_id)->lockForUpdate()->firstOrFail();
                if ((int) $titulo->empresa_id !== Tenant::id()) {
                    throw new RuntimeException('Título pertence a outra empresa.');
                }
                if ((int) $titulo->valor_centavos !== $evento->valorCentavos) {
                    throw new RuntimeException('Valor do título divergente.');
                }
            }

            DB::table('pix_eventos')->insert([
                'empresa_id' => Tenant::id(),
                'cobranca_pix_id' => $cobranca->id,
                'provedor' => $evento->provedor,
                'evento_externo' => $evento->eventoExterno,
                'tipo' => $evento->tipo,
                'impressao_payload' => $impressao,
                'resultado' => 'aguarda_baixa',
                'recebido_em' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            // Não atualizar status/recebido_em antes de homologação do provedor.
            return 'registrado_sem_baixa';
        }, 3);
    }
}
