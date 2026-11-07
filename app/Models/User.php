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

    protected $fillable = ['empresa_id', 'loja_id', 'name', 'email', 'telefone', 'perfil', 'comissao_percentual', 'ativo', 'password'];

    protected $hidden = ['dois_fatores_segredo', 'dois_fatores_recuperacao', 'password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acesso_em' => 'datetime',
            'password' => 'hashed',
            'perfil' => Perfil::class,
            'ativo' => 'boolean',
            'comissao_percentual' => 'decimal:2',
            'admin_plataforma' => 'boolean',
            'dois_fatores_segredo' => 'encrypted',
            'dois_fatores_recuperacao' => 'encrypted:array',
            'dois_fatores_ativo_em' => 'datetime',
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

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\RedefinirSenha($token));
    }

    public function doisFatoresAtivo(): bool
    {
        return $this->dois_fatores_ativo_em !== null && $this->dois_fatores_segredo;
    }

    /** Admin da plataforma e dono precisam de verificação em duas etapas. */
    public function exigeDoisFatores(): bool
    {
        return (bool) $this->admin_plataforma;
    }

    public static function daEmpresaAtual()
    {
        return static::query()->where('empresa_id', \App\Support\Tenant::id() ?? 0);
    }
}
