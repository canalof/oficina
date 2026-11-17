<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;

class CnhConsulta extends Model
{
    use PertenceAEmpresa;

    protected $table = 'cnh_consultas';
    protected $fillable = ['user_id', 'condutor_id', 'titular_hash', 'ordem_servico_id', 'provedor', 'finalidade', 'status', 'evidencias'];

    protected function casts(): array
    {
        return ['evidencias' => 'array'];
    }
}
