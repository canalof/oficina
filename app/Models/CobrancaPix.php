<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CobrancaPix extends Model
{
    use Auditavel, PertenceAEmpresa;

    protected $table = 'cobrancas_pix';
    protected $fillable = ['conta_bancaria_id', 'conta_receber_id', 'provedor', 'id_externo', 'txid', 'valor_centavos', 'status', 'expira_em', 'confirmado_em', 'pix_payload'];
    protected $hidden = ['pix_payload'];

    protected function casts(): array
    {
        return ['valor_centavos' => 'integer', 'expira_em' => 'datetime', 'confirmado_em' => 'datetime', 'pix_payload' => 'encrypted'];
    }

    public function contaBancaria(): BelongsTo
    {
        return $this->belongsTo(ContaBancaria::class);
    }

    public function contaReceber(): BelongsTo
    {
        return $this->belongsTo(ContaReceber::class);
    }

    public function pixManualDisponivel(): bool
    {
        return $this->provedor === 'pix_manual'
            && $this->status === 'pendente'
            && $this->contaReceber?->status === 'aberta'
            && $this->contaReceber->valor_centavos === $this->valor_centavos;
    }
}
