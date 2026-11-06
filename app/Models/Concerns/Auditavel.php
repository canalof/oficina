<?php

namespace App\Models\Concerns;

use App\Models\Auditoria;
use App\Support\Tenant;

/**
 * Registra em `auditoria` quem criou, alterou ou excluiu o registro, com os valores antes e depois.
 */
trait Auditavel
{
    public static function bootAuditavel(): void
    {
        static::created(fn ($m) => $m->auditar('criou', null, $m->getAttributes()));
        static::updated(function ($m) {
            $mudou = array_diff_key($m->getChanges(), array_flip(['updated_at']));
            if ($mudou) {
                $m->auditar('alterou', array_intersect_key($m->getOriginal(), $mudou), $mudou);
            }
        });
        static::deleted(fn ($m) => $m->auditar('excluiu', $m->getOriginal(), null));
    }

    protected function auditar(string $acao, ?array $antes, ?array $depois): void
    {
        $ocultos = array_flip(array_merge($this->getHidden(), ['password', 'remember_token']));
        Auditoria::create([
            'empresa_id' => $this->empresa_id ?? Tenant::id(),
            'user_id' => auth()->id(),
            'acao' => $acao,
            'modelo' => class_basename($this),
            'modelo_id' => $this->getKey(),
            'antes' => $antes ? array_diff_key($antes, $ocultos) : null,
            'depois' => $depois ? array_diff_key($depois, $ocultos) : null,
            'ip' => request()?->ip(),
        ]);
    }
}
