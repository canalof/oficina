<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;

class PlacaConsulta extends Model
{
    use PertenceAEmpresa;

    protected $fillable = ['user_id', 'placa', 'provedor', 'status', 'resposta', 'custo_centavos'];

    protected function casts(): array
    {
        return ['resposta' => 'array', 'custo_centavos' => 'integer'];
    }
}
