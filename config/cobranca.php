<?php

/*
 * Cobrança da assinatura das oficinas (plataforma Torque).
 * Sem chave, funciona em modo demonstração: "assinar" ativa na hora, sem cobrar.
 */
return [
    'provedor' => env('COBRANCA_PROVEDOR', 'demonstracao'), // asaas | demonstracao
    'asaas' => [
        'url' => env('ASAAS_URL', 'https://api-sandbox.asaas.com/v3'), // produção: https://api.asaas.com/v3
        'chave' => env('ASAAS_API_KEY'),
        'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
        'forma' => env('ASAAS_FORMA', 'UNDEFINED'), // UNDEFINED = cliente escolhe (boleto, Pix ou cartão)
    ],
    'dias_tolerancia' => (int) env('COBRANCA_DIAS_TOLERANCIA', 7), // atraso antes de suspender
];
