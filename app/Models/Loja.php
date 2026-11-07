<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;

class Loja extends Model
{
    use PertenceAEmpresa;

    protected $fillable = ['nome', 'cnpj', 'telefone', 'cep', 'logradouro', 'numero', 'bairro', 'cidade', 'uf', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}
