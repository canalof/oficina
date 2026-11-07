<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    protected $fillable = [
        'nome_fantasia', 'razao_social', 'cnpj', 'telefone', 'email', 'cidade', 'uf', 'ativo',
        'inscricao_estadual', 'inscricao_municipal', 'codigo_municipio', 'logradouro', 'numero', 'bairro', 'cep',
        'regime_tributario', 'cfop_padrao', 'csosn_padrao', 'item_lista_servico', 'aliquota_iss', 'serie_nfce',
        'fiscal_ambiente', 'fiscal_token', 'whatsapp_instancia',
        'plano_id', 'assinatura_status', 'trial_ate', 'cobranca_cliente_id', 'cobranca_assinatura_id', 'proximo_vencimento', 'suspensa_em',
        'estoque_negativo', 'agenda_capacidade', 'orcamento_validade_dias',
    ];

    public const STATUS = ['trial' => 'Teste grátis', 'ativa' => 'Ativa', 'inadimplente' => 'Pagamento atrasado', 'suspensa' => 'Suspensa', 'cancelada' => 'Cancelada'];

    public const DIAS_TRIAL = 14;

    protected $hidden = ['fiscal_token'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean', 'fiscal_token' => 'encrypted', 'aliquota_iss' => 'decimal:2', 'regime_tributario' => 'integer', 'serie_nfce' => 'integer',
            'trial_ate' => 'date', 'proximo_vencimento' => 'date', 'suspensa_em' => 'datetime', 'estoque_negativo' => 'boolean',
            'agenda_capacidade' => 'integer', 'orcamento_validade_dias' => 'integer'];
    }

    public function optanteSimples(): bool
    {
        return in_array((int) $this->regime_tributario, [1, 2], true);
    }

    public function plano(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Plano::class);
    }

    /** Sem acesso às telas de trabalho (só à assinatura). */
    public function bloqueada(): bool
    {
        return in_array($this->assinatura_status, ['suspensa', 'cancelada'], true)
            || ($this->assinatura_status === 'trial' && $this->trial_ate && $this->trial_ate->lt(today()));
    }

    public function diasDeTeste(): ?int
    {
        return $this->assinatura_status === 'trial' && $this->trial_ate ? max(0, (int) today()->diffInDays($this->trial_ate, false)) : null;
    }

    public function lojas(): HasMany
    {
        return $this->hasMany(Loja::class);
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
