<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;

class MobilidadeFrota extends Model
{
    use Auditavel, PertenceAEmpresa;

    protected $table = 'mobilidade_frota';

    protected $fillable = ['placa', 'descricao', 'km', 'situacao', 'manutencao'];

    protected function casts(): array
    {
        return ['km' => 'integer'];
    }
}
