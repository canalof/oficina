<?php

namespace Tests\Unit;

use App\Support\Placa;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PlacaTest extends TestCase
{
    public function test_normaliza_removendo_traco_espaco_e_minusculas(): void
    {
        $this->assertSame('ABC1234', Placa::normalizar(' abc-1234 '));
        $this->assertSame('FTR4E21', Placa::normalizar('ftr 4e21'));
        $this->assertSame('', Placa::normalizar(null));
    }

    #[DataProvider('placas')]
    public function test_valida_formatos(string $placa, bool $valida): void
    {
        $this->assertSame($valida, Placa::valida(Placa::normalizar($placa)));
    }

    public static function placas(): array
    {
        return [
            'antiga' => ['ABC1234', true],
            'mercosul' => ['ABC1D23', true],
            'com traço' => ['ABC-1234', true],
            'curta' => ['AB1234', false],
            'letra no fim' => ['ABC123D', false],
            'número no início' => ['1BC1234', false],
            'vazia' => ['', false],
        ];
    }

    public function test_identifica_mercosul_e_formata(): void
    {
        $this->assertTrue(Placa::mercosul('ABC1D23'));
        $this->assertFalse(Placa::mercosul('ABC1234'));
        $this->assertSame('ABC-1234', Placa::formatar('ABC1234'));
        $this->assertSame('ABC1D23', Placa::formatar('ABC1D23'));
    }

    public function test_equivalentes_converte_entre_antiga_e_mercosul(): void
    {
        // 5º caractere: 0..9 <-> A..J
        $this->assertEqualsCanonicalizing(['ABC1234', 'ABC1C34'], Placa::equivalentes('ABC1234'));
        $this->assertEqualsCanonicalizing(['FTR4E21', 'FTR4421'], Placa::equivalentes('FTR4E21'));
        $this->assertEqualsCanonicalizing(['XYZ9A00', 'XYZ9000'], Placa::equivalentes('XYZ9000'));
    }
}
