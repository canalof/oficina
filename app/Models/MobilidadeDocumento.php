<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;

class MobilidadeDocumento extends Model
{
    use Auditavel, PertenceAEmpresa;

    protected $table = 'mobilidade_documentos';

    protected $fillable = ['reserva_id', 'retirada_id', 'user_id', 'tipo', 'texto', 'aceite_nome'];

    protected function casts(): array
    {
        return ['texto' => 'encrypted'];
    }
}
