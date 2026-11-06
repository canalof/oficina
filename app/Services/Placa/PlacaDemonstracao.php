<?php

namespace App\Services\Placa;

/**
 * Provedor de demonstração: não chama nenhuma API e não tem custo.
 * Usado em desenvolvimento, nos testes e enquanto a chave do provedor real não está configurada.
 */
class PlacaDemonstracao implements ProvedorPlaca
{
    private const CATALOGO = [
        ['Volkswagen', 'Gol', '1.0 MPI Total Flex', 'Prata', 'Flex', '005340-6', 4890000],
        ['Fiat', 'Strada', 'Freedom 1.3 CD', 'Branco', 'Flex', '001545-8', 10350000],
        ['Chevrolet', 'Onix', '1.0 Turbo LT', 'Preto', 'Flex', '004506-3', 8120000],
        ['Hyundai', 'HB20', '1.0 Comfort', 'Cinza', 'Flex', '015172-6', 6780000],
        ['Toyota', 'Corolla', '2.0 XEi', 'Branco', 'Flex', '002148-4', 12990000],
        ['Renault', 'Kwid', '1.0 Zen', 'Vermelho', 'Flex', '025271-9', 5190000],
        ['Jeep', 'Renegade', '1.3 T270 Longitude', 'Azul', 'Flex', '015156-4', 11450000],
        ['Honda', 'Civic', '2.0 EXL', 'Prata', 'Flex', '014084-8', 11980000],
    ];

    public function nome(): string
    {
        return 'demonstracao';
    }

    public function custoCentavos(): int
    {
        return 0;
    }

    public function consultar(string $placa): ?DadosVeiculo
    {
        if (str_starts_with($placa, 'ZZZ')) {
            return null; // placas ZZZ simulam "não encontrado"
        }
        $i = crc32($placa) % count(self::CATALOGO);
        [$marca, $modelo, $versao, $cor, $comb, $fipe, $valor] = self::CATALOGO[$i];
        $ano = 2012 + (crc32(strrev($placa)) % 13);

        return new DadosVeiculo(
            placa: $placa,
            marca: $marca,
            modelo: $modelo,
            versao: $versao,
            anoFabricacao: $ano,
            anoModelo: $ano + 1,
            cor: $cor,
            combustivel: $comb,
            fipeCodigo: $fipe,
            fipeValorCentavos: $valor,
            municipio: 'Campinas',
            uf: 'SP',
        );
    }
}
