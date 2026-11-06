<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\PertenceAEmpresa;
use App\Support\Dinheiro;
use App\Support\StatusOs;
use App\Support\Tenant;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrdemServico extends Model
{
    use Auditavel, PertenceAEmpresa, SoftDeletes;

    protected $table = 'ordens_servico';

    protected $fillable = [
        'veiculo_id', 'cliente_id', 'consultor_id', 'mecanico_id', 'km_entrada', 'combustivel',
        'defeito_relatado', 'diagnostico', 'observacoes', 'checklist', 'desconto_centavos',
        'previsao_entrega', 'garantia_dias',
    ];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'status' => StatusOs::class,
            'checklist' => 'array',
            'previsao_entrega' => 'datetime',
            'enviado_em' => 'datetime',
            'aprovado_em' => 'datetime',
            'iniciado_em' => 'datetime',
            'concluido_em' => 'datetime',
            'entregue_em' => 'datetime',
            'cancelado_em' => 'datetime',
            'km_entrada' => 'integer',
            'combustivel' => 'integer',
            'desconto_centavos' => 'integer',
            'total_centavos' => 'integer',
            'garantia_dias' => 'integer',
        ];
    }

    // ---------- Relações ----------

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class)->withTrashed();
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    public function consultor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultor_id');
    }

    public function mecanico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mecanico_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(OrdemServicoItem::class)->orderBy('id');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(OrdemServicoFoto::class)->orderBy('id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(OrdemServicoEvento::class)->orderByDesc('id');
    }

    // ---------- Abertura ----------

    /** Abre uma OS com o próximo número da empresa. */
    public static function abrir(array $dados): self
    {
        return DB::transaction(function () use ($dados) {
            $ultimo = static::withTrashed()->lockForUpdate()->max('numero');
            $os = new static($dados);
            $os->numero = ((int) $ultimo) + 1;
            $os->status = StatusOs::Orcamento;
            $os->token = Str::random(40);
            $os->save();
            $os->registrar('OS aberta');

            if ($os->km_entrada && $os->veiculo && $os->km_entrada > (int) $os->veiculo->km_atual) {
                $os->veiculo->update(['km_atual' => $os->km_entrada]);
            }

            return $os;
        });
    }

    /**
     * Encontra a OS pelo link enviado ao cliente (página pública, sem login).
     * Único lugar onde se busca fora da empresa atual: o token é aleatório e único.
     * Define a empresa da OS para o resto da requisição.
     */
    public static function porToken(string $token): ?self
    {
        if (strlen($token) < 32) {
            return null;
        }
        $os = static::withoutGlobalScope('empresa')->where('token', $token)->first();
        if ($os) {
            Tenant::usar($os->empresa_id);
        }

        return $os;
    }

    // ---------- Valores ----------

    public function recalcular(): void
    {
        $soma = (int) $this->itens()->where('situacao', '<>', 'recusado')->sum('total_centavos');
        $this->desconto_centavos = min((int) $this->desconto_centavos, $soma);
        $this->total_centavos = $soma - $this->desconto_centavos;
        $this->saveQuietly();
    }

    public function subtotal(): int
    {
        return (int) $this->itens->where('situacao', '<>', 'recusado')->sum('total_centavos');
    }

    public function totalFormatado(): string
    {
        return Dinheiro::formatar($this->total_centavos);
    }

    public function temPendentes(): bool
    {
        return $this->itens()->where('situacao', 'pendente')->exists();
    }

    // ---------- Andamento ----------

    public function registrar(string $descricao): void
    {
        $this->eventos()->create(['descricao' => $descricao, 'user_id' => auth()->id(), 'created_at' => now()]);
    }

    private function exigir(array $permitidos, string $mensagem): void
    {
        if (! in_array($this->status, $permitidos, true)) {
            throw new DomainException($mensagem);
        }
    }

    private function mudar(StatusOs $novo, string $evento, array $extra = []): void
    {
        foreach ($extra as $k => $v) {
            $this->{$k} = $v;
        }
        $this->status = $novo;
        $this->save();
        $this->registrar($evento);
    }

    /** Orçamento enviado ao cliente (pelo WhatsApp ou link). */
    public function marcarEnviado(): void
    {
        if (! $this->itens()->exists()) {
            throw new DomainException('Adicione pelo menos um serviço ou peça antes de enviar o orçamento.');
        }
        $this->enviado_em = now();
        if ($this->status === StatusOs::Orcamento) {
            $this->mudar(StatusOs::Aguardando, 'Orçamento enviado ao cliente');
        } else {
            $this->save();
            $this->registrar('Itens adicionais enviados ao cliente');
        }
    }

    /**
     * Aprova os itens pendentes escolhidos e recusa os demais pendentes.
     *
     * @param  list<int>|null  $aprovados  null = aprovar todos os pendentes
     */
    public function aprovar(?array $aprovados, string $quem, ?string $ip = null): void
    {
        $this->exigir([StatusOs::Orcamento, StatusOs::Aguardando, StatusOs::Aprovada, StatusOs::EmServico], 'Esta OS não está aberta para aprovação.');
        if (! $this->temPendentes()) {
            throw new DomainException('Não há itens esperando aprovação.');
        }

        DB::transaction(function () use ($aprovados, $quem, $ip) {
            $pendentes = $this->itens()->where('situacao', 'pendente')->get();
            $sim = $nao = 0;
            foreach ($pendentes as $item) {
                $ok = $aprovados === null || in_array($item->id, $aprovados, true);
                $item->update(['situacao' => $ok ? 'aprovado' : 'recusado']);
                $ok ? $sim++ : $nao++;
            }
            $this->recalcular();

            $texto = $sim === 0
                ? "Orçamento recusado por {$quem}"
                : "Aprovado por {$quem}: {$sim} ".($sim === 1 ? 'item' : 'itens').($nao ? ", {$nao} recusado(s)" : '');

            $this->aprovado_em = now();
            $this->aprovado_por = mb_substr($quem, 0, 120);
            $this->aprovado_ip = $ip;

            if (in_array($this->status, [StatusOs::Orcamento, StatusOs::Aguardando], true)) {
                // Tudo recusado: volta para orçamento, a equipe decide o que fazer
                $this->mudar($sim ? StatusOs::Aprovada : StatusOs::Orcamento, $texto);
            } else {
                $this->save();
                $this->registrar($texto);
            }
        });
    }

    public function iniciar(): void
    {
        $this->exigir([StatusOs::Aprovada], 'Só dá para começar o serviço depois da aprovação.');
        $this->mudar(StatusOs::EmServico, 'Serviço iniciado', ['iniciado_em' => now()]);
    }

    public function concluir(): void
    {
        $this->exigir([StatusOs::EmServico, StatusOs::Aprovada], 'O serviço precisa estar aprovado ou em andamento.');
        if ($this->temPendentes()) {
            throw new DomainException('Há itens esperando aprovação do cliente. Aprove ou remova antes de concluir.');
        }
        $this->mudar(StatusOs::Pronta, 'Carro pronto para retirar', ['concluido_em' => now()]);
    }

    public function entregar(): void
    {
        $this->exigir([StatusOs::Pronta], 'Marque o carro como pronto antes de entregar.');
        $this->mudar(StatusOs::Entregue, 'Carro entregue ao cliente', ['entregue_em' => now()]);
    }

    public function cancelar(string $motivo): void
    {
        $this->exigir([StatusOs::Orcamento, StatusOs::Aguardando, StatusOs::Aprovada, StatusOs::EmServico, StatusOs::Pronta], 'Esta OS já foi encerrada.');
        $this->mudar(StatusOs::Cancelada, 'OS cancelada: '.$motivo, ['cancelado_em' => now(), 'motivo_cancelamento' => mb_substr($motivo, 0, 255)]);
    }

    // ---------- Apoio ----------

    public function numeroFormatado(): string
    {
        return str_pad((string) $this->numero, 5, '0', STR_PAD_LEFT);
    }

    public function linkAprovacao(): string
    {
        return route('orcamento.ver', $this->token);
    }

    public function garantiaAte(): ?\Carbon\CarbonInterface
    {
        return $this->entregue_em && $this->garantia_dias ? $this->entregue_em->copy()->addDays($this->garantia_dias) : null;
    }

    public function scopeBusca(Builder $q, ?string $termo): Builder
    {
        $termo = trim((string) $termo);
        if ($termo === '') {
            return $q;
        }
        $placa = \App\Support\Placa::normalizar($termo);

        return $q->where(function (Builder $w) use ($termo, $placa) {
            if (ctype_digit($termo)) {
                $w->orWhere('numero', (int) $termo);
            }
            $w->orWhereHas('veiculo', fn ($v) => $v->whereIn('placa', \App\Support\Placa::valida($placa) ? \App\Support\Placa::equivalentes($placa) : [$placa])->orWhere('placa', 'like', $placa.'%'))
                ->orWhereHas('cliente', fn ($c) => $c->where('nome', 'like', '%'.$termo.'%'));
        });
    }
}
