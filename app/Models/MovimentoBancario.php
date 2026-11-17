<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimentoBancario extends Model
{
    use Auditavel, PertenceAEmpresa;

    protected $table = 'movimentos_bancarios';
    protected $fillable = ['conta_bancaria_id', 'data_movimento', 'valor_centavos', 'descricao', 'origem', 'referencia_externa', 'hash_importacao', 'status_conciliacao', 'conta_receber_id', 'conta_pagar_id', 'criado_por'];

    protected function casts(): array
    {
        return ['data_movimento' => 'date', 'valor_centavos' => 'integer'];
    }

    public function contaBancaria(): BelongsTo
    {
        return $this->belongsTo(ContaBancaria::class);
    }
}
