<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContaPagar extends Model
{
    use Auditavel, PertenceAEmpresa;

    protected $table = 'contas_pagar';

    public const CATEGORIAS = ['Peças e fornecedores', 'Aluguel', 'Energia e água', 'Internet e telefone', 'Salários', 'Impostos', 'Ferramentas e equipamentos', 'Sistemas e serviços', 'Outros'];

    protected $fillable = ['fornecedor_id', 'compra_id', 'categoria', 'descricao', 'documento', 'valor_centavos', 'vencimento', 'status', 'pago_em', 'forma_paga'];

    protected function casts(): array
    {
        return ['vencimento' => 'date', 'pago_em' => 'datetime', 'valor_centavos' => 'integer'];
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class)->withTrashed();
    }

    public function vencida(): bool
    {
        return $this->status === 'aberta' && $this->vencimento->lt(today());
    }
}
