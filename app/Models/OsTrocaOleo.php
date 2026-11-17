<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;

class OsTrocaOleo extends Model
{
    use Auditavel, PertenceAEmpresa;

    protected $table = 'os_trocas_oleo';

    protected $fillable = ['especificacao', 'litros', 'filtro', 'km', 'realizada_em'];

    protected function casts(): array
    {
        return ['litros' => 'decimal:2', 'realizada_em' => 'date'];
    }
}
