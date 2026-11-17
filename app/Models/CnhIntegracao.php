<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;

class CnhIntegracao extends Model
{
    use Auditavel, PertenceAEmpresa;

    protected $table = 'cnh_integracoes';
    protected $fillable = ['provedor', 'credenciais', 'finalidades', 'ativa', 'autorizada_em', 'testada_em', 'teste_ok'];
    protected $hidden = ['credenciais'];

    protected function casts(): array
    {
        return ['credenciais' => 'encrypted:array', 'finalidades' => 'array', 'ativa' => 'boolean',
            'autorizada_em' => 'datetime', 'testada_em' => 'datetime', 'teste_ok' => 'boolean'];
    }
}
