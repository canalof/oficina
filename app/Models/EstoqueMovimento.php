<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstoqueMovimento extends Model
{
    use PertenceAEmpresa;

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'tipo', 'quantidade', 'saldo_anterior', 'saldo', 'custo_unitario_centavos', 'origem', 'origem_id', 'observacao', 'created_at'];

    protected function casts(): array
    {
        return ['quantidade' => 'decimal:3', 'saldo_anterior' => 'decimal:3', 'saldo' => 'decimal:3', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class)->withTrashed();
    }

    public function origemTexto(): string
    {
        return match ($this->origem) {
            'compra' => 'Nota de compra',
            'os' => 'OS',
            'ajuste' => 'Ajuste de inventário',
            'devolucao_cliente' => 'Devolução do cliente',
            'devolucao_fornecedor' => 'Devolução ao fornecedor',
            'perda' => 'Perda / uso interno',
            default => 'Entrada manual',
        };
    }
}
