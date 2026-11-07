<?php

/*
 * Notas fiscais pela Focus NFe (https://focusnfe.com.br).
 * O token de cada oficina fica em Empresa (criptografado no banco).
 * Sem token, as notas são geradas em modo demonstração (sem valor fiscal).
 *
 * Os padrões abaixo são comuns para oficinas no Simples Nacional, mas
 * CONFIRME COM O CONTADOR antes de emitir em produção.
 */
return [
    'focus' => [
        'homologacao' => 'https://homologacao.focusnfe.com.br',
        'producao' => 'https://api.focusnfe.com.br',
    ],
    'pis_cst' => env('FISCAL_PIS_CST', '49'),
    'cofins_cst' => env('FISCAL_COFINS_CST', '49'),
    'icms_origem' => '0',
];
