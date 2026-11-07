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

    protected $fillable = ['categoria_id', 'fabricante_id', 'codigo', 'codigo_barras', 'referencia_fabricante', 'nome', 'marca', 'unidade', 'ncm', 'localizacao', 'custo_centavos', 'ultimo_custo_centavos', 'preco_centavos', 'estoque_minimo', 'ativo'];

    public const UNIDADES = ['UN' => 'Unidade', 'PC' => 'Peça', 'JG' => 'Jogo', 'PAR' => 'Par', 'KIT' => 'Kit', 'CX' => 'Caixa', 'L' => 'Litro', 'ML' => 'Mililitro', 'KG' => 'Quilo', 'G' => 'Grama', 'M' => 'Metro'];

    protected function casts(): array
    {
        return [
            'custo_centavos' => 'integer', 'preco_centavos' => 'integer', 'ultimo_custo_centavos' => 'integer',
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
    public function categoria(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function fabricante(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Fabricante::class);
    }

    public function marcaNome(): ?string
    {
        return $this->fabricante?->nome ?? $this->marca;
    }

    /**
     * Movimenta o estoque e registra o histórico (quantidade positiva entra, negativa sai),
     * com saldo anterior, saldo novo, usuário e motivo.
     * Entradas com custo (compra ou entrada manual) recalculam o custo médio.
     * O saldo pode ficar negativo quando a oficina permite (ver Empresa::estoque_negativo).
     */
    public function movimentar(float $quantidade, string $tipo, string $origem, ?int $origemId = null, ?int $custoCentavos = null, ?string $observacao = null): EstoqueMovimento
    {
        return DB::transaction(function () use ($quantidade, $tipo, $origem, $origemId, $custoCentavos, $observacao) {
            $atual = static::whereKey($this->id)->lockForUpdate()->first(['estoque', 'custo_centavos']);
            $anterior = (float) $atual->estoque;
            $saldo = round($anterior + $quantidade, 3);
            $dados = ['estoque' => $saldo];

            if ($quantidade > 0 && $custoCentavos !== null && in_array($origem, ['compra', 'manual'], true)) {
                $base = max(0, $anterior);
                $dados['custo_centavos'] = $base > 0 && $atual->custo_centavos > 0
                    ? (int) round(($base * $atual->custo_centavos + $quantidade * $custoCentavos) / ($base + $quantidade))
                    : $custoCentavos;
                $dados['ultimo_custo_centavos'] = $custoCentavos;
            }
            static::whereKey($this->id)->update($dados);
            if (isset($dados['custo_centavos']) && $dados['custo_centavos'] !== (int) $atual->custo_centavos) {
                // Registra a mudança de custo no histórico de alterações
                Auditoria::create([
                    'empresa_id' => $this->empresa_id, 'user_id' => auth()->id(), 'acao' => 'alterou', 'modelo' => 'Produto', 'modelo_id' => $this->id,
                    'antes' => ['custo_centavos' => (int) $atual->custo_centavos], 'depois' => ['custo_centavos' => $dados['custo_centavos'], 'motivo' => 'Custo médio após entrada'],
                    'ip' => request()?->ip(),
                ]);
            }
            $this->forceFill($dados)->syncOriginalAttributes(array_keys($dados));

            return $this->movimentos()->create([
                'user_id' => auth()->id(), 'tipo' => $tipo, 'quantidade' => $quantidade, 'saldo_anterior' => $anterior, 'saldo' => $saldo,
                'custo_unitario_centavos' => $custoCentavos ?? $atual->custo_centavos, 'origem' => $origem, 'origem_id' => $origemId,
                'observacao' => $observacao ? mb_substr($observacao, 0, 255) : null, 'created_at' => now(),
            ]);
        });
    }

    /** Quantidade já prometida em OS aprovadas que ainda não foram entregues. */
    public function reservado(): float
    {
        return (float) OrdemServicoItem::where('produto_id', $this->id)->where('situacao', 'aprovado')->where('estoque_baixado', false)
            ->whereHas('ordemServico', fn ($q) => $q->whereIn('status', ['aprovada', 'em_servico', 'pronta']))
            ->sum('quantidade');
    }

    public function disponivel(): float
    {
        return round((float) $this->estoque - $this->reservado(), 3);
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
            ->orWhere('codigo', $termo)->orWhere('codigo_barras', $termo)->orWhere('referencia_fabricante', $termo)
            ->orWhere('marca', 'like', "%{$termo}%")->orWhereHas('fabricante', fn ($f) => $f->where('nome', 'like', "%{$termo}%")));
    }

    public function scopeAbaixoDoMinimo(Builder $q): Builder
    {
        return $q->where('estoque_minimo', '>', 0)->whereColumn('estoque', '<=', 'estoque_minimo');
    }
}
