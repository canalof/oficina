<?php

namespace App\Models\Concerns;

use App\Models\Empresa;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Isola os dados por empresa.
 *
 * - Toda consulta é filtrada pela empresa atual (Tenant::id()).
 * - Todo registro novo recebe o empresa_id da empresa atual.
 * - Sem empresa definida, a consulta não retorna nada e a gravação falha,
 *   para que um esquecimento nunca exponha dados de outro cliente.
 */
trait PertenceAEmpresa
{
    public static function bootPertenceAEmpresa(): void
    {
        static::addGlobalScope('empresa', function (Builder $query) {
            $id = Tenant::id();
            $coluna = $query->getModel()->qualifyColumn('empresa_id');
            $id === null ? $query->whereRaw('1 = 0') : $query->where($coluna, $id);
        });

        static::creating(function ($model) {
            if (! $model->empresa_id) {
                if (! Tenant::definido()) {
                    throw new RuntimeException('Empresa não definida ao gravar '.class_basename($model).'.');
                }
                $model->empresa_id = Tenant::id();
            }
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
