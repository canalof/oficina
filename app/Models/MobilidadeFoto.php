<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;

class MobilidadeFoto extends Model
{
    use Auditavel, PertenceAEmpresa;

    protected $table = 'mobilidade_fotos';

    protected $fillable = ['reserva_id', 'retirada_id', 'etapa', 'caminho'];

    protected $hidden = ['caminho'];
}
