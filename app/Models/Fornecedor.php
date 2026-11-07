<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use App\Support\Documento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fornecedor extends Model
{
    use Auditavel, PertenceAEmpresa, SoftDeletes;

    protected $table = 'fornecedores';

    protected $fillable = ['nome', 'cpf_cnpj', 'telefone', 'email', 'contato', 'cidade', 'uf', 'observacoes'];

    public function setCpfCnpjAttribute($v): void
    {
        $this->attributes['cpf_cnpj'] = Documento::normalizar($v) ?: null;
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class)->latest('emissao');
    }

    public function documentoFormatado(): string
    {
        return $this->cpf_cnpj ? Documento::formatar($this->cpf_cnpj) : '';
    }
}
