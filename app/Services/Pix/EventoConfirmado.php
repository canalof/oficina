<?php

namespace App\Services\Pix;

use InvalidArgumentException;

/** Evento normalizado APÓS validação criptográfica pelo adaptador do provedor. */
final readonly class EventoConfirmado
{
    public function __construct(
        public string $provedor,
        public string $eventoExterno,
        public string $cobrancaExterna,
        public string $txid,
        public int $valorCentavos,
        public string $tipo,
    ) {
        if ($provedor === '' || $eventoExterno === '' || $cobrancaExterna === '' || $txid === ''
            || strlen($provedor) > 60 || strlen($eventoExterno) > 190
            || strlen($cobrancaExterna) > 190 || strlen($txid) > 100
            || $valorCentavos <= 0 || !in_array($tipo, ['pago'], true)) {
            throw new InvalidArgumentException('Evento Pix inválido.');
        }
    }
}
