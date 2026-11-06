<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use App\Support\Documento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use Auditavel, PertenceAEmpresa, SoftDeletes;

    protected $fillable = [
        'tipo_pessoa', 'nome', 'cpf_cnpj', 'ie', 'telefone', 'whatsapp', 'aceita_whatsapp', 'email',
        'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'nascimento', 'observacoes',
    ];

    protected function casts(): array
    {
        return ['aceita_whatsapp' => 'boolean', 'nascimento' => 'date'];
    }

    public function ordens(): HasMany
    {
        return $this->hasMany(OrdemServico::class)->orderByDesc('numero');
    }

    public function veiculos(): HasMany
    {
        return $this->hasMany(Veiculo::class);
    }

    public function documentoFormatado(): string
    {
        return $this->cpf_cnpj ? Documento::formatar($this->cpf_cnpj) : '';
    }

    public function enderecoCompleto(): string
    {
        $linha1 = trim(implode(', ', array_filter([$this->logradouro, $this->numero, $this->complemento])));
        $linha2 = trim(implode(' - ', array_filter([$this->bairro, trim(($this->cidade ?? '').($this->uf ? '/'.$this->uf : ''))])));

        return trim(implode(' · ', array_filter([$linha1, $linha2])));
    }

    /** Link para conversa no WhatsApp (DDI 55 quando o número vier só com DDD). */
    public function linkWhatsapp(?string $texto = null): ?string
    {
        $n = preg_replace('/\D/', '', (string) ($this->whatsapp ?: $this->telefone));
        if (strlen($n) < 10) {
            return null;
        }
        if (strlen($n) <= 11) {
            $n = '55'.$n;
        }

        return 'https://wa.me/'.$n.($texto ? '?text='.rawurlencode($texto) : '');
    }

    public function scopeBusca(Builder $q, ?string $termo): Builder
    {
        $termo = trim((string) $termo);
        if ($termo === '') {
            return $q;
        }
        $digitos = preg_replace('/\D/', '', $termo);

        return $q->where(function (Builder $w) use ($termo, $digitos) {
            $w->where('nome', 'like', "%{$termo}%")->orWhere('email', 'like', "%{$termo}%");
            if (strlen($digitos) >= 3) {
                $w->orWhere('cpf_cnpj', 'like', "%{$digitos}%")
                    ->orWhere('telefone', 'like', "%{$digitos}%")
                    ->orWhere('whatsapp', 'like', "%{$digitos}%");
            }
        });
    }
}
