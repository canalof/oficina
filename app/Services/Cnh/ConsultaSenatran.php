<?php

namespace App\Services\Cnh;

/**
 * Nome legado mantido apenas por compatibilidade.
 *
 * Antes esta classe duplicava o cliente HTTP da SENATRAN com a URL de
 * homologação fixa no código. Agora ela delega para SenatranProvider, que
 * resolve o endereço por ambiente a partir de config/cnh.php.
 */
class ConsultaSenatran extends SenatranProvider
{
}
