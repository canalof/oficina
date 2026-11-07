<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Catálogo de serviços da oficina, com preço padrão. */
class Servico extends Model
{
    use Auditavel, PertenceAEmpresa, SoftDeletes;

    protected $fillable = ['nome', 'preco_centavos', 'tempo_minutos', 'garantia_dias', 'revisao_meses', 'revisao_km', 'ativo'];

    protected function casts(): array
    {
        return ['preco_centavos' => 'integer', 'tempo_minutos' => 'integer', 'garantia_dias' => 'integer', 'revisao_meses' => 'integer', 'revisao_km' => 'integer', 'ativo' => 'boolean'];
    }
}
