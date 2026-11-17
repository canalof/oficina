<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;

class ModeloOs extends Model
{
    use Auditavel, PertenceAEmpresa;

    protected $table = 'modelos_os';
    protected $fillable = ['nome', 'servico_ids', 'produto_ids', 'ativo'];

    protected function casts(): array
    {
        return ['servico_ids' => 'array', 'produto_ids' => 'array', 'ativo' => 'boolean'];
    }
}
