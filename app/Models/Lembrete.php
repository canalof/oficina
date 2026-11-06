<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lembrete de revisão gerado na entrega do carro (ex.: troca de óleo daqui a 6 meses). */
class Lembrete extends Model
{
    use PertenceAEmpresa;

    protected $fillable = ['veiculo_id', 'cliente_id', 'ordem_servico_id', 'descricao', 'data_prevista', 'km_previsto', 'status', 'enviado_em'];

    protected function casts(): array
    {
        return ['data_prevista' => 'date', 'enviado_em' => 'datetime', 'km_previsto' => 'integer'];
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class)->withTrashed();
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    public function mensagem(string $oficina): string
    {
        $nome = $this->cliente ? ', '.explode(' ', trim($this->cliente->nome))[0] : '';
        $km = $this->km_previsto ? ' ou aos '.number_format($this->km_previsto, 0, ',', '.').' km' : '';

        return "Olá{$nome}! Aqui é da {$oficina}. Está chegando a hora de: {$this->descricao} do seu "
            .trim($this->veiculo->descricao()).' ('.$this->veiculo->placaFormatada().'), previsto para '
            .$this->data_prevista->format('d/m/Y').$km.'. Quer agendar? É só responder esta mensagem.';
    }
}
