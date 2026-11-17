<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;

class MobilidadeModelo extends Model
{
    use Auditavel, PertenceAEmpresa;

    protected $table = 'mobilidade_modelos';

    protected $fillable = ['tipo', 'texto'];
}
