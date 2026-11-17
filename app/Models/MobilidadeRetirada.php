<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;

class MobilidadeRetirada extends Model
{
    use Auditavel, PertenceAEmpresa;

    protected $table = 'mobilidade_retiradas';

    protected $fillable = ['ordem_servico_id', 'condutor_id', 'conferencia_id', 'user_id', 'terceiro', 'autorizacao', 'justificativa', 'aceite_nome', 'checklist', 'entregue_em'];

    protected function casts(): array
    {
        return ['terceiro' => 'boolean', 'autorizacao' => 'encrypted', 'checklist' => 'encrypted', 'entregue_em' => 'datetime'];
    }
}
