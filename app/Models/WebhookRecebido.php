<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookRecebido extends Model
{
    protected $table = 'webhooks_recebidos';

    protected $fillable = ['provedor', 'evento_id', 'evento', 'payload', 'processado_em', 'erro'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'processado_em' => 'datetime'];
    }
}
