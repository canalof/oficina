<?php

namespace App\Services\Cnh;

use App\Models\Empresa;

/**
 * F-247-04: resolve a retenção efetiva de evidências de CNH sem permitir
 * "ilimitado" por acidente.
 *
 * Precedência: valor da empresa -> padrão da instalação -> limite técnico de
 * segurança (365 dias), marcado como política ainda não definida. Valores fora
 * de 30..3650 (inclusive 0) são inválidos e nunca são interpretados como
 * retenção infinita.
 */
class PoliticaRetencaoCnh
{
    public const MINIMO = 30;

    public const MAXIMO = 3650;

    public const TECNICO = 365;

    /**
     * @return array{dias:int, origem:string, definida:bool}
     */
    public function efetiva(?Empresa $empresa = null): array
    {
        $daEmpresa = $this->normalizar($empresa?->cnh_retencao_dias);
        if ($daEmpresa !== null) {
            return ['dias' => $daEmpresa, 'origem' => 'empresa', 'definida' => true];
        }

        $daInstalacao = $this->normalizar(config('cnh.retencao_dias'));
        if ($daInstalacao !== null) {
            return ['dias' => $daInstalacao, 'origem' => 'instalacao', 'definida' => true];
        }

        return ['dias' => (int) config('cnh.retencao_limites.tecnico', self::TECNICO), 'origem' => 'tecnico', 'definida' => false];
    }

    public function valido(mixed $valor): bool
    {
        return $this->normalizar($valor) !== null;
    }

    public function normalizar(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (! is_numeric($valor)) {
            return null;
        }

        $dias = (int) $valor;
        $minimo = (int) config('cnh.retencao_limites.minimo', self::MINIMO);
        $maximo = (int) config('cnh.retencao_limites.maximo', self::MAXIMO);

        return $dias >= $minimo && $dias <= $maximo ? $dias : null;
    }
}
