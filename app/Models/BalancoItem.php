<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalancoItem extends Model
{
    use PertenceAEmpresa;

    public $timestamps = false;

    protected $table = 'balanco_itens';

    protected $fillable = ['balanco_id', 'produto_id', 'saldo_visto', 'saldo_sistema', 'contado', 'diferenca', 'custo_unitario_centavos'];

    protected function casts(): array
    {
        return ['saldo_visto' => 'decimal:3', 'saldo_sistema' => 'decimal:3', 'contado' => 'decimal:3', 'diferenca' => 'decimal:3', 'custo_unitario_centavos' => 'integer'];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class)->withTrashed();
    }

    /** O saldo mudou (venda, entrada) entre a geração da lista e a aplicação do balanço. */
    public function mudouDurante(): bool
    {
        return $this->saldo_visto !== null && round((float) $this->saldo_visto - (float) $this->saldo_sistema, 3) != 0.0;
    }
}
