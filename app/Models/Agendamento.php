<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use App\Support\Placa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agendamento extends Model
{
    use Auditavel, PertenceAEmpresa;

    public const STATUS = ['agendado' => 'Agendado', 'confirmado' => 'Confirmado', 'chegou' => 'Chegou', 'faltou' => 'Não veio', 'cancelado' => 'Cancelado'];

    protected $fillable = ['cliente_id', 'veiculo_id', 'ordem_servico_id', 'user_id', 'nome', 'telefone', 'placa', 'inicio', 'duracao_minutos', 'servico', 'status', 'lembrete_enviado_em', 'observacoes'];

    protected function casts(): array
    {
        return ['inicio' => 'datetime', 'lembrete_enviado_em' => 'datetime'];
    }

    public function setPlacaAttribute($v): void
    {
        $this->attributes['placa'] = Placa::normalizar($v) ?: null;
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class)->withTrashed();
    }

    public function statusCor(): string
    {
        return match ($this->status) {
            'confirmado' => 'border-l-ok',
            'chegou' => 'border-l-placa',
            'faltou', 'cancelado' => 'border-l-erro opacity-60',
            default => 'border-l-sinal',
        };
    }

    public function linkWhatsapp(string $texto): ?string
    {
        $n = preg_replace('/\D/', '', (string) ($this->telefone ?: $this->cliente?->whatsapp ?: $this->cliente?->telefone));
        if (strlen($n) < 10) {
            return null;
        }

        return 'https://wa.me/'.(strlen($n) <= 11 ? '55'.$n : $n).'?text='.rawurlencode($texto);
    }
}
