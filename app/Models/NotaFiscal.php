<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaFiscal extends Model
{
    use PertenceAEmpresa;

    protected $table = 'notas_fiscais';

    protected $fillable = ['ordem_servico_id', 'user_id', 'tipo', 'referencia', 'provedor', 'ambiente', 'status', 'numero', 'serie', 'chave', 'valor_centavos', 'url_pdf', 'url_xml', 'mensagem', 'envio', 'resposta', 'autorizada_em', 'cancelada_em'];

    protected function casts(): array
    {
        return ['envio' => 'array', 'resposta' => 'array', 'autorizada_em' => 'datetime', 'cancelada_em' => 'datetime', 'valor_centavos' => 'integer'];
    }

    public function ordemServico(): BelongsTo
    {
        return $this->belongsTo(OrdemServico::class);
    }

    public function tipoRotulo(): string
    {
        return ['nfse' => 'NFS-e (serviços)', 'nfce' => 'NFC-e (peças)', 'nfe' => 'NF-e (peças)'][$this->tipo] ?? $this->tipo;
    }

    public function statusRotulo(): string
    {
        return ['processando' => 'Processando', 'autorizada' => 'Autorizada', 'erro' => 'Com erro', 'cancelada' => 'Cancelada'][$this->status] ?? $this->status;
    }

    public function statusCor(): string
    {
        return match ($this->status) {
            'autorizada' => 'border-[#b7dcc6] bg-[#eef8f2] text-ok',
            'erro' => 'border-[#ecc3bf] bg-[#fcf0ef] text-erro',
            'cancelada' => 'border-linha bg-white text-suave',
            default => 'border-[#f0d58a] bg-[#fff7df] text-sinal-escuro',
        };
    }

    public function demonstracao(): bool
    {
        return $this->provedor === 'demonstracao';
    }
}
