<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;

class OrdemServicoInspecao extends Model
{
    use PertenceAEmpresa;

    public const GRAVIDADES = ['ok' => 'OK', 'acompanhar' => 'Acompanhar', 'imediata' => 'Atenção imediata'];

    protected $table = 'ordem_servico_inspecoes';

    protected $fillable = ['componente', 'gravidade', 'observacao', 'foto_id', 'item_id', 'revisar_em', 'revisar_km'];

    protected function casts(): array
    {
        return ['revisar_em' => 'date'];
    }
}
