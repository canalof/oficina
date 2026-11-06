<?php

namespace Tests\Unit;

use App\Support\Documento;
use PHPUnit\Framework\TestCase;

class DocumentoTest extends TestCase
{
    public function test_cpf(): void
    {
        $this->assertTrue(Documento::valido('529.982.247-25'));
        $this->assertTrue(Documento::cpfValido('39053344705'));
        $this->assertFalse(Documento::cpfValido('52998224724'));
        $this->assertFalse(Documento::cpfValido('11111111111'), 'sequência repetida não vale');
        $this->assertSame('PF', Documento::tipo('52998224725'));
    }

    public function test_cnpj_numerico(): void
    {
        $this->assertTrue(Documento::valido('11.222.333/0001-81'));
        $this->assertFalse(Documento::cnpjValido('11222333000182'));
        $this->assertFalse(Documento::cnpjValido('00000000000000'));
        $this->assertSame('PJ', Documento::tipo('11222333000181'));
    }

    public function test_cnpj_alfanumerico_de_2026(): void
    {
        // Exemplo oficial da Receita Federal para o CNPJ alfanumérico.
        $this->assertTrue(Documento::cnpjValido('12ABC34501DE35'));
        $this->assertTrue(Documento::valido('12.ABC.345/01DE-35'));
        $this->assertFalse(Documento::cnpjValido('12ABC34501DE36'));
    }

    public function test_formatar(): void
    {
        $this->assertSame('529.982.247-25', Documento::formatar('52998224725'));
        $this->assertSame('11.222.333/0001-81', Documento::formatar('11222333000181'));
    }
}
