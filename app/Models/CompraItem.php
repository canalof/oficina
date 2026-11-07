<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompraItem extends Model
{
    use PertenceAEmpresa;

    protected $table = 'compra_itens';

    protected $fillable = ['produto_id', 'codigo_fornecedor', 'codigo_barras', 'descricao', 'ncm', 'unidade', 'quantidade', 'custo_unitario_centavos', 'total_centavos'];

    protected function casts(): array
    {
        return ['quantidade' => 'decimal:3', 'custo_unitario_centavos' => 'integer', 'total_centavos' => 'integer'];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class)->withTrashed();
    }
}
