<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;

class MensagemWhatsapp extends Model
{
    use PertenceAEmpresa;

    protected $table = 'mensagens_whatsapp';

    protected $fillable = ['empresa_id', 'destino', 'tipo', 'referencia_id', 'texto', 'status', 'tentativas', 'erro', 'enviada_em'];

    protected function casts(): array
    {
        return ['enviada_em' => 'datetime'];
    }
}
