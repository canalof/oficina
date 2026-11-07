<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;

class FaturaPlataforma extends Model
{
    use PertenceAEmpresa;

    protected $table = 'faturas_plataforma';

    protected $fillable = ['empresa_id', 'externo_id', 'valor_centavos', 'vencimento', 'status', 'url', 'paga_em'];

    protected function casts(): array
    {
        return ['vencimento' => 'date', 'paga_em' => 'datetime', 'valor_centavos' => 'integer'];
    }
}
