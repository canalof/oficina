<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Auditoria extends Model
{
    protected $table = 'auditoria';

    public const UPDATED_AT = null;

    protected $fillable = ['empresa_id', 'user_id', 'acao', 'modelo', 'modelo_id', 'antes', 'depois', 'ip'];

    protected function casts(): array
    {
        return ['antes' => 'array', 'depois' => 'array'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
