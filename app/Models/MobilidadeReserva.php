<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;

class MobilidadeReserva extends Model
{
    use Auditavel, PertenceAEmpresa;

    protected $table = 'mobilidade_reservas';

    protected $fillable = ['ordem_servico_id', 'frota_id', 'condutor_id', 'conta_receber_id', 'tipo', 'parceira', 'contrato_externo', 'inicio', 'fim', 'status', 'diaria_centavos', 'taxas_centavos', 'caucao_centavos', 'entrega', 'devolucao', 'ocorrencias'];

    protected function casts(): array
    {
        return ['inicio' => 'datetime', 'fim' => 'datetime', 'diaria_centavos' => 'integer', 'taxas_centavos' => 'integer', 'caucao_centavos' => 'integer', 'entrega' => 'array', 'devolucao' => 'array'];
    }
}
