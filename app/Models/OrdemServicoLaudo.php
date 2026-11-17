<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;

class OrdemServicoLaudo extends Model
{
    use Auditavel, PertenceAEmpresa;

    public const RODAS = ['de' => 'Dianteira esquerda', 'dd' => 'Dianteira direita', 'te' => 'Traseira esquerda', 'td' => 'Traseira direita'];
    public const CAMPOS = [
        'convergencia_antes' => 'Convergência antes (°)',
        'convergencia_depois' => 'Convergência depois (°)',
        'cambagem_antes' => 'Cambagem antes (°)',
        'cambagem_depois' => 'Cambagem depois (°)',
        'sulco' => 'Sulco (mm)',
    ];

    protected $table = 'ordem_servico_laudos';

    protected $fillable = ['medicoes', 'recomendacoes', 'publicado'];

    protected function casts(): array
    {
        return ['medicoes' => 'array', 'publicado' => 'boolean'];
    }
}
