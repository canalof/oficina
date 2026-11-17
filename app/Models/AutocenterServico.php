<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutocenterServico extends Model
{
    use PertenceAEmpresa;

    public const TIPOS = ['alinhamento', 'balanceamento', 'rodizio', 'cambagem', 'caster', 'troca_pneus', 'reparo_pneu'];
    public const POSICOES = ['dianteiro_esquerdo', 'dianteiro_direito', 'traseiro_esquerdo', 'traseiro_direito', 'estepe'];
    protected $table = 'autocenter_servicos';
    protected $fillable = ['veiculo_id', 'ordem_servico_id', 'user_id', 'tipo', 'km_registrado', 'posicao_origem', 'posicao_destino', 'observacoes'];
    protected function casts(): array { return ['km_registrado' => 'integer']; }
    public function veiculo(): BelongsTo { return $this->belongsTo(Veiculo::class); }
    public function ordemServico(): BelongsTo { return $this->belongsTo(OrdemServico::class, 'ordem_servico_id'); }
}
