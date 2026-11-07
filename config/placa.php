<?php

return [
    // demonstracao | consultadeplaca
    'provedor' => env('PLACA_PROVEDOR', 'demonstracao'),

    // Reaproveita uma consulta bem-sucedida da mesma placa por este número de dias (sem custo)
    'reuso_dias' => (int) env('PLACA_REUSO_DIAS', 30),

    'consultadeplaca' => [
        'chave' => env('PLACA_API_KEY'),
        'url' => env('PLACA_API_URL', 'https://consultadeplaca.net/api/v1'),
        'custo_centavos' => (int) env('PLACA_CUSTO_CENTAVOS', 7),
    ],
];
