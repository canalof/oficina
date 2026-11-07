<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdemServicoEvento extends Model
{
    use PertenceAEmpresa;

    protected $table = 'ordem_servico_eventos';

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'descricao', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
