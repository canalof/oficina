<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdemServicoFoto extends Model
{
    use PertenceAEmpresa;

    protected $table = 'ordem_servico_fotos';

    protected $fillable = ['user_id', 'caminho', 'legenda'];

    public function ordemServico(): BelongsTo
    {
        return $this->belongsTo(OrdemServico::class);
    }
}
