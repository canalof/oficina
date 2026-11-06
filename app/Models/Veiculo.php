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
        'cliente_id', 'placa', 'marca', 'modelo', 'versao', 'ano_fabricacao', 'ano_modelo', 'cor',
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

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
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

    /** Troca o dono e guarda o histórico. */
    public function transferirPara(?Cliente $novo): void
    {
        DB::transaction(function () use ($novo) {
            $hoje = now()->toDateString();
            VeiculoDono::where('veiculo_id', $this->id)->whereNull('fim')->update(['fim' => $hoje]);
            if ($novo) {
                VeiculoDono::create(['veiculo_id' => $this->id, 'cliente_id' => $novo->id, 'inicio' => $hoje]);
            }
            $this->cliente_id = $novo?->id;
            $this->save();
        });
    }
}
