<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContaBancaria extends Model
{
    use Auditavel, PertenceAEmpresa;

    protected $table = 'contas_bancarias';
    protected $hidden = ['numero_conta', 'pix_chave'];
    protected $fillable = ['apelido', 'banco_codigo', 'banco_nome', 'agencia', 'numero_conta', 'tipo', 'saldo_inicial_centavos', 'ativa', 'pix_tipo', 'pix_chave', 'pix_recebedor', 'pix_cidade'];

    protected function casts(): array
    {
        return ['saldo_inicial_centavos' => 'integer', 'ativa' => 'boolean', 'pix_chave' => 'encrypted'];
    }

    public function movimentos(): HasMany
    {
        return $this->hasMany(MovimentoBancario::class);
    }
}
