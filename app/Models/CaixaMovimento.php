<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaixaMovimento extends Model
{
    use PertenceAEmpresa;

    public const UPDATED_AT = null;

    protected $fillable = ['caixa_id', 'user_id', 'tipo', 'forma', 'valor_centavos', 'descricao', 'conta_receber_id', 'conta_pagar_id', 'created_at'];

    protected function casts(): array
    {
        return ['valor_centavos' => 'integer', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entrada(): bool
    {
        return in_array($this->tipo, ['recebimento', 'suprimento'], true);
    }
}
