<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    use Auditavel, PertenceAEmpresa;

    private const PADROES = [
        'Filtros', 'Óleos e lubrificantes', 'Freios', 'Suspensão e direção',
        'Pneus e rodas', 'Elétrica e baterias', 'Motor e ignição',
        'Arrefecimento', 'Correias e tensores', 'Transmissão e embreagem',
        'Palhetas e acessórios', 'Consumíveis',
    ];

    protected $fillable = ['nome', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class);
    }

    public static function criarPadroes(int $empresaId): void
    {
        $agora = now();
        static::query()->insertOrIgnore(array_map(fn ($nome) => [
            'empresa_id' => $empresaId,
            'nome' => $nome,
            'ativo' => true,
            'created_at' => $agora,
            'updated_at' => $agora,
        ], self::PADROES));
    }
}
