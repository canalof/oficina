<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdemServicoItem extends Model
{
    use PertenceAEmpresa;

    protected $table = 'ordem_servico_itens';

    protected $fillable = ['tipo', 'servico_id', 'produto_id', 'estoque_baixado', 'descricao', 'quantidade', 'valor_unitario_centavos', 'situacao', 'mecanico_id'];

    protected function casts(): array
    {
        return ['quantidade' => 'decimal:3', 'valor_unitario_centavos' => 'integer', 'total_centavos' => 'integer', 'estoque_baixado' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $item->total_centavos = (int) round((float) $item->quantidade * $item->valor_unitario_centavos);
        });
    }

    public function ordemServico(): BelongsTo
    {
        return $this->belongsTo(OrdemServico::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class)->withTrashed();
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class)->withTrashed();
    }

    public function mecanico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mecanico_id');
    }

    /** "1", "2,5" (sem zeros sobrando). */
    public function quantidadeFormatada(): string
    {
        return rtrim(rtrim(number_format((float) $this->quantidade, 3, ',', '.'), '0'), ',');
    }
}
