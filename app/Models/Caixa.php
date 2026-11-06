<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Caixa do dia: abre com um troco, recebe e paga, fecha conferindo o dinheiro da gaveta. */
class Caixa extends Model
{
    use PertenceAEmpresa;

    protected $fillable = ['aberto_por', 'fechado_por', 'aberto_em', 'fechado_em', 'saldo_inicial_centavos', 'dinheiro_esperado_centavos', 'dinheiro_contado_centavos', 'observacao'];

    protected function casts(): array
    {
        return ['aberto_em' => 'datetime', 'fechado_em' => 'datetime'];
    }

    public static function aberto(): ?self
    {
        return static::whereNull('fechado_em')->latest('id')->first();
    }

    public function movimentos(): HasMany
    {
        return $this->hasMany(CaixaMovimento::class)->orderByDesc('id');
    }

    public function abertoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aberto_por');
    }

    public function fechadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fechado_por');
    }

    /** @return array<string,int> total por forma (só entradas de recebimento) */
    public function recebidoPorForma(): array
    {
        return $this->movimentos()->where('tipo', 'recebimento')->selectRaw('forma, sum(valor_centavos) as total')
            ->groupBy('forma')->pluck('total', 'forma')->map(fn ($v) => (int) $v)->all();
    }

    /** Quanto deveria haver de dinheiro na gaveta. */
    public function dinheiroEsperado(): int
    {
        $soma = fn (string $tipo) => (int) $this->movimentos()->where('tipo', $tipo)->where('forma', 'dinheiro')->sum('valor_centavos');

        return $this->saldo_inicial_centavos + $soma('recebimento') + $soma('suprimento') - $soma('sangria') - $soma('pagamento');
    }
}
