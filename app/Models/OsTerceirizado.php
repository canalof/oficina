<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;

class OsTerceirizado extends Model
{
    use Auditavel, PertenceAEmpresa;

    protected $table = 'os_terceirizados';

    protected $fillable = ['servico', 'parceiro', 'responsavel', 'custo_centavos', 'previsao', 'status'];

    protected function casts(): array
    {
        return ['previsao' => 'date', 'custo_centavos' => 'integer'];
    }
}
