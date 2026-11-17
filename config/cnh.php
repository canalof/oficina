<?php

return [
    /*
     * Ambiente efetivo das consultas de CNH.
     *
     * "homologacao" usa as URLs de homologação e NUNCA libera retirada ou locação.
     * "producao" só funciona com endpoint explícito em variável de ambiente; o
     * código não presume nenhuma URL de produção. Sem endpoint configurado, o
     * fornecedor recusa a consulta com mensagem explícita e nada é enviado.
     */
    'ambiente' => strtolower((string) env('CNH_AMBIENTE', 'homologacao')) === 'producao' ? 'producao' : 'homologacao',

    'endpoints' => [
        'senatran' => [
            'homologacao' => (string) env('CNH_SENATRAN_URL_HOMOLOGACAO', 'https://hom-wsdenatran.serpro.gov.br'),
            'producao' => (string) env('CNH_SENATRAN_URL_PRODUCAO', ''),
        ],
        'vio' => [
            'homologacao' => (string) env('CNH_VIO_URL_HOMOLOGACAO', 'https://gateway.apiserpro.serpro.gov.br'),
            'producao' => (string) env('CNH_VIO_URL_PRODUCAO', ''),
            'token' => (string) env('CNH_VIO_TOKEN_PATH', '/token'),
            'decode' => (string) env('CNH_VIO_DECODE_PATH', '/viodec-trial/v1/decode'),
        ],
    ],

    'senatran' => [
        // Recurso relativo; mantido em configuração para auditoria do contrato.
        'recurso' => '/v1/condutores/cpf/%s/registroCnh/%s',
    ],

    'http' => [
        'conectar_em' => (int) env('CNH_HTTP_CONNECT_TIMEOUT', 5),
        'tempo_total' => (int) env('CNH_HTTP_TIMEOUT', 12),
    ],

    // Chave por ID de oficina: certificado (PEM), chave (PEM), finalidades,
    // operadores (ID do usuário => CPF) e autorizacao_validada (boolean).
    // Configure apenas depois de confirmar o fluxo autorizado com o Serpro/GCC.
    'oficinas' => json_decode((string) env('CNH_HOMOLOGACAO_OFICINAS', '{}'), true) ?: [],
    'vio' => json_decode((string) env('CNH_VIO_HOMOLOGACAO_OFICINAS', '{}'), true) ?: [],
    // Retenção de evidências de CNH (F-247-04).
    // 0/ausente = política ainda não definida; nunca significa retenção ilimitada.
    // Cada empresa pode definir cnh_retencao_dias; o limite técnico de segurança
    // (365 dias) só é usado enquanto não houver política aprovada.
    'retencao_dias' => (int) env('CNH_BYOK_RETENCAO_DIAS', 0),
    'expurgo_minimo_dias' => 30,
    'retencao_limites' => ['minimo' => 30, 'maximo' => 3650, 'tecnico' => 365],
    // Expurgo permanece desligado nesta etapa; só um plano de retenção aprovado
    // pode habilitar CNH_EXPURGO_ATIVO=true depois de teste em homologação.
    'expurgo_ativo' => filter_var(env('CNH_EXPURGO_ATIVO', false), FILTER_VALIDATE_BOOL),
    // TORQUE-CNH-249: preservação legal. Linhas antigas vinculadas a uma
    // conferência oficial (evidencias.conferencia_id) ou a uma ordem de serviço
    // não são expurgadas pelo comando de retenção de consultas: elas instruem
    // outro registro e seguem o prazo desse registro.
    'expurgo_preservar' => [
        'conferencia' => filter_var(env('CNH_EXPURGO_PRESERVAR_CONFERENCIA', true), FILTER_VALIDATE_BOOL),
        'ordem_servico' => filter_var(env('CNH_EXPURGO_PRESERVAR_ORDEM_SERVICO', true), FILTER_VALIDATE_BOOL),
    ],
    // Tamanho do lote de exclusão por empresa (ids por iteração).
    'expurgo_lote' => (int) env('CNH_EXPURGO_LOTE', 500),
];
