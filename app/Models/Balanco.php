<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Balanço de estoque: várias contagens aplicadas de uma vez (Services/Estoque/AplicarBalanco). */
class Balanco extends Model
{
    use Auditavel, PertenceAEmpresa;

    protected $fillable = ['user_id', 'observacao', 'produtos_contados', 'produtos_ajustados', 'diferenca_valor_centavos'];

    protected function casts(): array
    {
        return ['produtos_contados' => 'integer', 'produtos_ajustados' => 'integer', 'diferenca_valor_centavos' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(BalancoItem::class);
    }
}
