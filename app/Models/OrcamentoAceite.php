<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro imutável do que o cliente aprovou (itens, valores, versão, quem, quando, de onde). */
class OrcamentoAceite extends Model
{
    use PertenceAEmpresa;

    public const UPDATED_AT = null;

    protected $fillable = ['ordem_servico_id', 'user_id', 'versao', 'canal', 'nome', 'ip', 'navegador', 'itens', 'total_centavos', 'hash', 'created_at'];

    protected function casts(): array
    {
        return ['itens' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => false); // não se altera
        static::deleting(fn () => false);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Confere se o registro não foi mexido no banco. */
    public function integro(): bool
    {
        return hash_equals($this->hash, self::calcularHash($this->ordem_servico_id, $this->versao, $this->nome, $this->itens, $this->total_centavos, $this->created_at->toIso8601String()));
    }

    public static function calcularHash(int $osId, int $versao, string $nome, array $itens, int $total, string $quando): string
    {
        return hash_hmac('sha256', json_encode([$osId, $versao, $nome, $itens, $total, $quando], JSON_UNESCAPED_UNICODE), (string) config('app.key'));
    }
}
