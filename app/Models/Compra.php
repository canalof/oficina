<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compra extends Model
{
    use Auditavel, PertenceAEmpresa;

    protected $fillable = ['fornecedor_id', 'user_id', 'numero', 'serie', 'chave', 'emissao', 'total_centavos', 'duplicatas', 'status', 'xml_caminho', 'lancada_em', 'observacoes'];

    protected function casts(): array
    {
        return ['emissao' => 'date', 'duplicatas' => 'array', 'lancada_em' => 'datetime', 'total_centavos' => 'integer'];
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class)->withTrashed();
    }

    public function itens(): HasMany
    {
        return $this->hasMany(CompraItem::class)->orderBy('id');
    }

    public function lancada(): bool
    {
        return $this->status === 'lancada';
    }
}
