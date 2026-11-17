<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MobilidadeCondutor extends Model
{
    use Auditavel, PertenceAEmpresa, SoftDeletes;

    protected $table = 'mobilidade_condutores';

    protected $fillable = ['nome', 'cpf', 'registro_cnh', 'contato', 'categoria', 'validade'];

    protected $hidden = ['cpf', 'registro_cnh', 'contato'];

    protected function casts(): array
    {
        return ['cpf' => 'encrypted', 'registro_cnh' => 'encrypted', 'contato' => 'encrypted', 'validade' => 'date'];
    }
}
