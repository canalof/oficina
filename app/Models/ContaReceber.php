<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContaReceber extends Model
{
    use Auditavel, PertenceAEmpresa;

    protected $table = 'contas_receber';

    protected $fillable = ['cliente_id', 'ordem_servico_id', 'descricao', 'parcela', 'valor_centavos', 'vencimento', 'forma', 'status', 'recebido_em', 'forma_recebida'];

    protected function casts(): array
    {
        return ['vencimento' => 'date', 'recebido_em' => 'datetime', 'valor_centavos' => 'integer'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    public function ordemServico(): BelongsTo
    {
        return $this->belongsTo(OrdemServico::class);
    }

    public function vencida(): bool
    {
        return $this->status === 'aberta' && $this->vencimento->lt(today());
    }
}
