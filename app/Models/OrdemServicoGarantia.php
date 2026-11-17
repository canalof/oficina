<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;

class OrdemServicoGarantia extends Model
{
    use Auditavel, PertenceAEmpresa;

    public const STATUS = ['aberta' => 'Aberta', 'fornecedor' => 'Aguardando fornecedor', 'troca' => 'Troca concluída', 'credito' => 'Crédito confirmado', 'recusada' => 'Recusada'];

    protected $table = 'ordem_servico_garantias';

    protected $fillable = ['peca', 'fornecedor', 'prazo', 'relato', 'status', 'resolucao', 'foto_id', 'custo_pecas_centavos', 'custo_mao_obra_centavos'];

    protected function casts(): array
    {
        return ['prazo' => 'date'];
    }
}
