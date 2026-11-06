<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    protected $fillable = ['nome_fantasia', 'razao_social', 'cnpj', 'telefone', 'email', 'cidade', 'uf', 'plano', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
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
