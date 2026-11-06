<?php

namespace App\Support;

/**
 * Caminho de uma ordem de serviço:
 * Orçamento → Aguardando aprovação → Aprovada → Em serviço → Pronta → Entregue
 * (Cancelada a qualquer momento antes da entrega.)
 */
enum StatusOs: string
{
    case Orcamento = 'orcamento';
    case Aguardando = 'aguardando';
    case Aprovada = 'aprovada';
    case EmServico = 'em_servico';
    case Pronta = 'pronta';
    case Entregue = 'entregue';
    case Cancelada = 'cancelada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Orcamento => 'Orçamento',
            self::Aguardando => 'Aguardando aprovação',
            self::Aprovada => 'Aprovada',
            self::EmServico => 'Em serviço',
            self::Pronta => 'Pronta para retirar',
            self::Entregue => 'Entregue',
            self::Cancelada => 'Cancelada',
        };
    }

    /** Classes do selo de status. */
    public function cor(): string
    {
        return match ($this) {
            self::Orcamento => 'border-linha bg-[#f3f4f6] text-suave',
            self::Aguardando => 'border-[#f0d58a] bg-[#fff7df] text-sinal-escuro',
            self::Aprovada => 'border-[#c4d3f0] bg-[#eef3fc] text-placa',
            self::EmServico => 'border-[#c4d3f0] bg-[#eef3fc] text-placa',
            self::Pronta => 'border-[#b7dcc6] bg-[#eef8f2] text-ok',
            self::Entregue => 'border-linha bg-white text-suave',
            self::Cancelada => 'border-[#ecc3bf] bg-[#fcf0ef] text-erro',
        };
    }

    /** Ainda está no pátio (não entregue nem cancelada). */
    public function noPatio(): bool
    {
        return ! in_array($this, [self::Entregue, self::Cancelada], true);
    }

    /** Pode receber itens novos. */
    public function aceitaItens(): bool
    {
        return in_array($this, [self::Orcamento, self::Aguardando, self::Aprovada, self::EmServico], true);
    }

    /** @return list<self> */
    public static function doPatio(): array
    {
        return [self::Orcamento, self::Aguardando, self::Aprovada, self::EmServico, self::Pronta];
    }
}
