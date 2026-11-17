<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;

class OsCompraPendente extends Model
{
    use Auditavel, PertenceAEmpresa;

    protected $table = 'os_compras_pendentes';

    protected $fillable = ['item_id', 'compra_item_id', 'cotacoes', 'escolhida'];

    protected function casts(): array
    {
        return ['cotacoes' => 'array', 'escolhida' => 'integer'];
    }
}
