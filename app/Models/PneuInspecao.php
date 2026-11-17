<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PneuInspecao extends Model
{
    use PertenceAEmpresa;

    public const POSICOES = ['dianteiro_esquerdo', 'dianteiro_direito', 'traseiro_esquerdo', 'traseiro_direito', 'estepe'];

    protected $table = 'pneu_inspecoes';
    protected $fillable = ['veiculo_id', 'user_id', 'posicao', 'medida', 'marca', 'dot', 'sulco_decimos_mm', 'km_registrado', 'observacoes'];

    protected function casts(): array { return ['sulco_decimos_mm' => 'integer', 'km_registrado' => 'integer']; }
    public function veiculo(): BelongsTo { return $this->belongsTo(Veiculo::class); }
    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}
