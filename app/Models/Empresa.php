<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    protected $fillable = [
        'nome_fantasia', 'razao_social', 'cnpj', 'telefone', 'email', 'cidade', 'uf', 'plano', 'ativo',
        'inscricao_estadual', 'inscricao_municipal', 'codigo_municipio', 'logradouro', 'numero', 'bairro', 'cep',
        'regime_tributario', 'cfop_padrao', 'csosn_padrao', 'item_lista_servico', 'aliquota_iss', 'serie_nfce',
        'fiscal_ambiente', 'fiscal_token', 'whatsapp_instancia',
    ];

    protected $hidden = ['fiscal_token'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean', 'fiscal_token' => 'encrypted', 'aliquota_iss' => 'decimal:2', 'regime_tributario' => 'integer', 'serie_nfce' => 'integer'];
    }

    public function optanteSimples(): bool
    {
        return in_array((int) $this->regime_tributario, [1, 2], true);
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
