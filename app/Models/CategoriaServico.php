<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaServico extends Model
{
    use Auditavel, PertenceAEmpresa;

    protected $table = 'categorias_servico';
    protected $fillable = ['nome', 'ativo'];

    public function servicos(): HasMany
    {
        return $this->hasMany(Servico::class);
    }

    public static function criarPadroes(int $empresaId): void
    {
        $categorias = [
            'Revisão preventiva', 'Troca de óleo e filtros', 'Freios', 'Suspensão e direção',
            'Alinhamento e balanceamento', 'Elétrica', 'Ar-condicionado', 'Motor e diagnóstico', 'Pneus',
        ];
        $agora = now();
        static::query()->insertOrIgnore(array_map(fn ($nome) => [
            'empresa_id' => $empresaId, 'nome' => $nome, 'ativo' => true,
            'created_at' => $agora, 'updated_at' => $agora,
        ], $categorias));
    }
}
