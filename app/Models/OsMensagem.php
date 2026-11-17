<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use App\Support\Perfil;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OsMensagem extends Model
{
    use PertenceAEmpresa;

    protected $table = 'os_mensagens';
    protected $fillable = ['user_id', 'perfil_mencionado', 'mensagem'];

    protected function casts(): array
    {
        return ['perfil_mencionado' => Perfil::class];
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
