<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Produto extends Model
{
    use Auditavel, PertenceAEmpresa, SoftDeletes;

    protected $fillable = ['codigo', 'codigo_barras', 'nome', 'marca', 'unidade', 'ncm', 'localizacao', 'custo_centavos', 'preco_centavos', 'estoque_minimo', 'ativo'];

    protected function casts(): array
    {
        return [
            'custo_centavos' => 'integer', 'preco_centavos' => 'integer',
            'estoque' => 'decimal:3', 'estoque_minimo' => 'decimal:3', 'ativo' => 'boolean',
        ];
    }

    public function movimentos(): HasMany
    {
        return $this->hasMany(EstoqueMovimento::class)->orderByDesc('id');
    }

    /**
     * Movimenta o estoque e registra o histórico (quantidade positiva entra, negativa sai).
     * O saldo pode ficar negativo: a oficina não deve travar um serviço por erro de cadastro,
     * mas o produto aparece como "estoque negativo" para ser corrigido.
     */
    public function movimentar(float $quantidade, string $tipo, string $origem, ?int $origemId = null, ?int $custoCentavos = null, ?string $observacao = null): EstoqueMovimento
    {
        return DB::transaction(function () use ($quantidade, $tipo, $origem, $origemId, $custoCentavos, $observacao) {
            $atual = static::whereKey($this->id)->lockForUpdate()->value('estoque');
            $saldo = round((float) $atual + $quantidade, 3);
            static::whereKey($this->id)->update(['estoque' => $saldo]);
            $this->estoque = $saldo;

            return $this->movimentos()->create([
                'user_id' => auth()->id(), 'tipo' => $tipo, 'quantidade' => $quantidade, 'saldo' => $saldo,
                'custo_unitario_centavos' => $custoCentavos, 'origem' => $origem, 'origem_id' => $origemId,
                'observacao' => $observacao, 'created_at' => now(),
            ]);
        });
    }

    public function noMinimo(): bool
    {
        return (float) $this->estoque_minimo > 0 && (float) $this->estoque <= (float) $this->estoque_minimo;
    }

    public static function formatarQtd(mixed $q): string
    {
        return rtrim(rtrim(number_format((float) $q, 3, ',', '.'), '0'), ',');
    }

    public function estoqueFormatado(): string
    {
        return self::formatarQtd($this->estoque).' '.$this->unidade;
    }

    public function scopeBusca(Builder $q, ?string $termo): Builder
    {
        $termo = trim((string) $termo);

        return $termo === '' ? $q : $q->where(fn ($w) => $w->where('nome', 'like', "%{$termo}%")
            ->orWhere('codigo', $termo)->orWhere('codigo_barras', $termo)->orWhere('marca', 'like', "%{$termo}%"));
    }

    public function scopeAbaixoDoMinimo(Builder $q): Builder
    {
        return $q->where('estoque_minimo', '>', 0)->whereColumn('estoque', '<=', 'estoque_minimo');
    }
}
