<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;

class AgendaSolicitacao extends Model
{
    use Auditavel, PertenceAEmpresa;

    protected $table = 'agenda_solicitacoes';

    protected $fillable = ['cliente_id', 'veiculo_id', 'agendamento_id', 'preferencia', 'servico', 'status'];

    protected function casts(): array
    {
        return ['preferencia' => 'datetime'];
    }

    public function veiculo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function agendamento(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Agendamento::class);
    }
}
