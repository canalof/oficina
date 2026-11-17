<?php

namespace App\Services\Cnh;

interface ProvedorCnh
{
    /**
     * Autenticidade que o próprio adaptador declara para a fonte consultada.
     *
     * Valores possíveis: 'oficial' (base oficial do RENACH/SENATRAN, somente
     * quando o contrato permite), 'documental' (o adaptador apenas lê o
     * documento/QR apresentado) ou 'nao_verificada'. O nome do fornecedor, por
     * si só, nunca eleva a autenticidade: quem declara é o adaptador e a
     * avaliação ainda aplica a lista fechada de fontes oficiais.
     */
    public function autenticidade(): string;

    public function regras(): array;

    public function testar(#[\SensitiveParameter] array $credenciais, #[\SensitiveParameter] array $entrada = []): void;

    public function consultar(#[\SensitiveParameter] array $credenciais, #[\SensitiveParameter] array $entrada): array;
}
