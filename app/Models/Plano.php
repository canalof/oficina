<?php

namespace App\Models;

use App\Support\Dinheiro;
use Illuminate\Database\Eloquent\Model;

/** Plano da plataforma (dado global, gerenciado só pelo admin da plataforma). */
class Plano extends Model
{
    protected $fillable = ['nome', 'slug', 'preco_centavos', 'limite_usuarios', 'limite_lojas', 'limite_consultas_placa', 'whatsapp_automatico', 'ativo', 'ordem'];

    protected function casts(): array
    {
        return ['preco_centavos' => 'integer', 'whatsapp_automatico' => 'boolean', 'ativo' => 'boolean'];
    }

    public function precoFormatado(): string
    {
        return Dinheiro::formatar($this->preco_centavos);
    }

    public function limiteTexto(?int $v, string $unidade): string
    {
        return $v === null ? $unidade.' ilimitados' : $v.' '.$unidade;
    }
}
