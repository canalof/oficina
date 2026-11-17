<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;

class MobilidadeReserva extends Model
{
    use Auditavel, PertenceAEmpresa;

    protected $table = 'mobilidade_reservas';

    public const MODALIDADES = ['carro_reserva' => 'Carro reserva', 'aluguel' => 'Aluguel'];

    public const ORIGENS = ['frota' => 'Frota própria', 'parceira' => 'Locadora parceira'];

    protected $fillable = ['modalidade', 'cliente_id', 'ordem_servico_id', 'frota_id', 'condutor_id', 'conta_receber_id', 'tipo', 'parceira', 'contrato_externo', 'inicio', 'fim', 'status', 'diaria_centavos', 'taxas_centavos', 'caucao_centavos', 'entrega', 'devolucao', 'ocorrencias'];

    protected function casts(): array
    {
        return ['inicio' => 'datetime', 'fim' => 'datetime', 'diaria_centavos' => 'integer', 'taxas_centavos' => 'integer', 'caucao_centavos' => 'integer', 'entrega' => 'array', 'devolucao' => 'array'];
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function ordemServico()
    {
        return $this->belongsTo(OrdemServico::class);
    }

    public function frota()
    {
        return $this->belongsTo(MobilidadeFrota::class, 'frota_id');
    }

    public function condutor()
    {
        return $this->belongsTo(MobilidadeCondutor::class, 'condutor_id')->withTrashed();
    }

    public function ehAluguel(): bool
    {
        return $this->modalidade === 'aluguel';
    }
}
