<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Support\Perfil;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Usuário do sistema. Não usa o escopo PertenceAEmpresa porque o login
 * precisa encontrar o usuário antes de saber a empresa; as telas de usuários
 * filtram por empresa explicitamente (User::daEmpresaAtual()).
 */
class User extends Authenticatable
{
    use Auditavel, Notifiable;

    protected $fillable = ['empresa_id', 'loja_id', 'name', 'email', 'telefone', 'perfil', 'ativo', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acesso_em' => 'datetime',
            'password' => 'hashed',
            'perfil' => Perfil::class,
            'ativo' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class);
    }

    public function pode(string $permissao): bool
    {
        return $this->ativo && $this->perfil->pode($permissao);
    }

    public static function daEmpresaAtual()
    {
        return static::query()->where('empresa_id', \App\Support\Tenant::id() ?? 0);
    }
}
