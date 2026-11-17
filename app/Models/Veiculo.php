<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use App\Support\Placa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Veiculo extends Model
{
    use Auditavel, PertenceAEmpresa, SoftDeletes;

    protected $fillable = [
        'cliente_id', 'placa', 'marca', 'modelo', 'versao', 'motor', 'renavam', 'ano_fabricacao', 'ano_modelo', 'cor',
        'combustivel', 'chassi', 'km_atual', 'fipe_codigo', 'fipe_valor_centavos', 'observacoes',
    ];

    protected function casts(): array
    {
        return ['km_atual' => 'integer', 'fipe_valor_centavos' => 'integer'];
    }

    public function setPlacaAttribute($valor): void
    {
        $this->attributes['placa'] = Placa::normalizar($valor);
    }

    public function setRenavamAttribute($valor): void
    {
        $this->attributes['renavam'] = \App\Support\Renavam::normalizar($valor) ?: null;
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function ordens(): HasMany
    {
        return $this->hasMany(OrdemServico::class)->orderByDesc('numero');
    }

    public function donos(): HasMany
    {
        return $this->hasMany(VeiculoDono::class)->orderByDesc('inicio')->orderByDesc('id');
    }

    public function placaFormatada(): string
    {
        return Placa::formatar($this->placa);
    }

    public function descricao(): string
    {
        $ano = $this->ano_fabricacao && $this->ano_modelo
            ? $this->ano_fabricacao.'/'.$this->ano_modelo
            : ($this->ano_modelo ?: $this->ano_fabricacao);

        return trim(implode(' ', array_filter([$this->marca, $this->modelo, $this->versao, $ano])));
    }

    /** Busca pela placa nas duas formas (antiga e Mercosul). */
    public static function porPlaca(?string $placa): ?self
    {
        return static::query()->whereIn('placa', Placa::equivalentes($placa))->first();
    }

    public function scopeBusca(Builder $q, ?string $termo): Builder
    {
        $termo = trim((string) $termo);
        if ($termo === '') {
            return $q;
        }
        $placa = Placa::normalizar($termo);

        return $q->where(function (Builder $w) use ($termo, $placa) {
            $w->where('placa', 'like', "%{$placa}%")
                ->orWhere('modelo', 'like', "%{$termo}%")
                ->orWhere('marca', 'like', "%{$termo}%")
                ->orWhereHas('cliente', fn ($c) => $c->where('nome', 'like', "%{$termo}%"));
            if (Placa::valida($placa)) {
                $w->orWhereIn('placa', Placa::equivalentes($placa));
            }
        });
    }

    /** Troca o dono uma única vez, com o veículo bloqueado e histórico consistente. */
    public function transferirPara(?Cliente $novo): void
    {
        DB::transaction(function () use ($novo) {
            $atual = static::whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            if ($novo) {
                // A validação no controller não substitui a validação na escrita.
                // Rejeita também um cliente removido após o formulário ter sido aberto.
                if (! Cliente::whereKey($novo->getKey())->exists()) {
                    throw new \DomainException('O novo proprietário não pertence a esta oficina ou foi excluído.');
                }
            }
            $novoId = $novo?->getKey();
            if ((int) $atual->cliente_id === (int) $novoId) {
                $this->cliente_id = $atual->cliente_id;
                $this->syncOriginalAttribute('cliente_id');
                return; // Idempotente: não fecha/reabre histórico do mesmo dono.
            }

            $hoje = now()->toDateString();
            VeiculoDono::where('veiculo_id', $atual->id)->whereNull('fim')->update(['fim' => $hoje]);
            if ($novoId !== null) {
                VeiculoDono::create(['veiculo_id' => $atual->id, 'cliente_id' => $novoId, 'inicio' => $hoje]);
            }
            $atual->cliente_id = $novoId;
            $atual->save();
            $this->cliente_id = $novoId;
            $this->syncOriginalAttribute('cliente_id');
        }, 3);
    }
}
