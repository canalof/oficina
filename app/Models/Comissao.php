<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comissao extends Model
{
    use PertenceAEmpresa;

    protected $table = 'comissoes';

    protected $fillable = ['user_id', 'ordem_servico_id', 'base_centavos', 'percentual', 'valor_centavos', 'competencia', 'paga_em'];

    protected function casts(): array
    {
        return ['competencia' => 'date', 'paga_em' => 'datetime', 'percentual' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ordemServico(): BelongsTo
    {
        return $this->belongsTo(OrdemServico::class);
    }
}
