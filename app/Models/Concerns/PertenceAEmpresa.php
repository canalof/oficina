<?php

namespace App\Models\Concerns;

use App\Models\Empresa;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
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
            $tenant = Tenant::id();
            if ($tenant === null) {
                throw new RuntimeException('Empresa não definida ao gravar '.class_basename($model).'.');
            }
            // Não permitir que atribuições diretas contornem o tenant atual.
            if ($model->empresa_id !== null && (int) $model->empresa_id !== $tenant) {
                throw new RuntimeException('Empresa divergente ao criar '.class_basename($model).'.');
            }
            $model->empresa_id = $tenant;
        });

        static::updating(function ($model) {
            self::validarPropriedadeTenant($model, 'alterar');
        });

        // delete() em uma instância Eloquent pode usar uma consulta sem o escopo
        // global. Validar também a operação de exclusão (e restauração soft-delete).
        static::deleting(function ($model) {
            self::validarPropriedadeTenant($model, 'excluir');
        });

        // restoring() é fornecido SOMENTE pelo trait SoftDeletes. Chamar
        // static::restoring() em modelos sem SoftDeletes cai em __callStatic,
        // instancia o modelo durante o boot e derruba TODA consulta Laravel 13.
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restoring(function ($model) {
                self::validarPropriedadeTenant($model, 'restaurar');
            });
        }
    }

    private static function validarPropriedadeTenant($model, string $acao): void
    {
        $tenant = Tenant::id();
        if ($tenant === null || (int) $model->empresa_id !== $tenant
            || (int) $model->getRawOriginal('empresa_id') !== $tenant) {
            throw new RuntimeException('Operação de '.$acao.' entre empresas bloqueada para '.class_basename($model).'.');
        }
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
