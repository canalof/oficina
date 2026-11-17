<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConferenciaCnh extends Model
{
    use PertenceAEmpresa;

    public const FINALIDADES = ['retirada' => 'Retirada do veículo', 'locacao' => 'Locação'];
    public const METODOS = ['documento' => 'Documento apresentado', 'vio' => 'Autenticidade pelo QR Code', 'situacao' => 'Situação atual apresentada pelo condutor'];
    public const RESULTADOS = ['conferido' => 'Sem divergências na conferência', 'divergente' => 'Divergência encontrada', 'inconclusivo' => 'Conferência inconclusiva'];

    protected $table = 'conferencias_cnh';
    protected $fillable = ['ordem_servico_id', 'user_id', 'condutor', 'finalidade', 'metodo', 'resultado', 'condutor_id', 'evidencias'];

    protected function casts(): array
    {
        return ['evidencias' => 'array'];
    }

    public function ordemServico(): BelongsTo
    {
        return $this->belongsTo(OrdemServico::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
