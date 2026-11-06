<?php

namespace App\Services\Fiscal;

use App\Models\Empresa;
use App\Models\NotaFiscal;
use App\Models\OrdemServico;
use App\Support\Documento;
use App\Support\StatusOs;
use DomainException;
use Illuminate\Support\Str;

/**
 * Monta e envia as notas de uma OS:
 * - NFS-e para os serviços (prefeitura);
 * - NFC-e (consumidor) ou NF-e (empresa que pede nota) para as peças.
 */
class NotasFiscais
{
    public function emissor(Empresa $empresa): Emissor
    {
        return $empresa->fiscal_token
            ? new FocusNfe($empresa->fiscal_token, $empresa->fiscal_ambiente)
            : new EmissorDemonstracao;
    }

    public function emitir(OrdemServico $os, string $tipo): NotaFiscal
    {
        if (! in_array($tipo, ['nfse', 'nfce', 'nfe'], true)) {
            throw new DomainException('Tipo de nota inválido.');
        }
        if (! in_array($os->status, [StatusOs::Pronta, StatusOs::Entregue], true) || ! $os->recebido_em) {
            throw new DomainException('Emita a nota depois de registrar o pagamento da OS.');
        }
        if ($os->notas()->where('tipo', $tipo)->whereIn('status', ['processando', 'autorizada'])->exists()) {
            throw new DomainException('Já existe uma nota deste tipo para esta OS.');
        }

        $os->load(['cliente', 'veiculo', 'itens.produto', 'contasReceber']);
        $empresa = Empresa::findOrFail($os->empresa_id);
        $emissor = $this->emissor($empresa);
        $real = $emissor->nome() !== 'demonstracao';

        [$dados, $valor] = match ($tipo) {
            'nfse' => $this->nfse($os, $empresa, $real),
            default => $this->produtos($os, $empresa, $tipo, $real),
        };

        $nota = NotaFiscal::create([
            'ordem_servico_id' => $os->id, 'user_id' => auth()->id(), 'tipo' => $tipo,
            'referencia' => 'os'.$os->id.'-'.$tipo.'-'.Str::lower(Str::random(6)),
            'provedor' => $emissor->nome(), 'ambiente' => $real ? $empresa->fiscal_ambiente : 'demonstracao',
            'valor_centavos' => $valor, 'envio' => $dados,
        ]);
        $this->aplicar($nota, $emissor->enviar($tipo, $nota->referencia, $dados));
        $os->registrar(['nfse' => 'NFS-e', 'nfce' => 'NFC-e', 'nfe' => 'NF-e'][$tipo].' '.($nota->status === 'erro' ? 'recusada' : ($nota->status === 'autorizada' ? 'autorizada' : 'enviada')).($real ? '' : ' (demonstração)'));

        return $nota;
    }

    public function atualizar(NotaFiscal $nota): NotaFiscal
    {
        $empresa = Empresa::findOrFail($nota->empresa_id);
        $this->aplicar($nota, $this->emissor($empresa)->consultar($nota->tipo, $nota->referencia));

        return $nota;
    }

    public function cancelar(NotaFiscal $nota, string $justificativa): NotaFiscal
    {
        if ($nota->status !== 'autorizada') {
            throw new DomainException('Só é possível cancelar nota autorizada.');
        }
        if (mb_strlen($justificativa) < 15) {
            throw new DomainException('A justificativa precisa ter pelo menos 15 caracteres.');
        }
        $empresa = Empresa::findOrFail($nota->empresa_id);
        $this->aplicar($nota, $this->emissor($empresa)->cancelar($nota->tipo, $nota->referencia, $justificativa));

        return $nota;
    }

    private function aplicar(NotaFiscal $nota, RespostaFiscal $r): void
    {
        $nota->fill([
            'status' => $r->status,
            'numero' => $r->numero ?? $nota->numero,
            'serie' => $r->serie ?? $nota->serie,
            'chave' => $r->chave ?? $nota->chave,
            'url_pdf' => $r->urlPdf ?? $nota->url_pdf,
            'url_xml' => $r->urlXml ?? $nota->url_xml,
            'mensagem' => $r->mensagem,
            'resposta' => $r->bruto ?: $nota->resposta,
        ]);
        if ($r->status === 'autorizada' && ! $nota->autorizada_em) {
            $nota->autorizada_em = now();
        }
        if ($r->status === 'cancelada' && ! $nota->cancelada_em) {
            $nota->cancelada_em = now();
        }
        $nota->save();
    }

    // ---------- montagem ----------

    private function exigirEmpresa(Empresa $e, array $campos): void
    {
        $faltam = array_filter($campos, fn ($c, $k) => blank($e->{$k}), ARRAY_FILTER_USE_BOTH);
        if ($faltam) {
            throw new DomainException('Preencha em Empresa → Dados fiscais: '.implode(', ', $faltam).'.');
        }
    }

    private function nfse(OrdemServico $os, Empresa $e, bool $real): array
    {
        $valor = $os->valorServicos();
        if ($valor <= 0) {
            throw new DomainException('Esta OS não tem serviços aprovados.');
        }
        if ($real) {
            $this->exigirEmpresa($e, ['cnpj' => 'CNPJ', 'inscricao_municipal' => 'inscrição municipal', 'codigo_municipio' => 'código do município (IBGE)']);
        }
        $servicos = $os->itens->where('situacao', 'aprovado')->where('tipo', 'servico');
        $discriminacao = 'Serviços no veículo '.$os->veiculo->descricao().' placa '.$os->veiculo->placaFormatada()
            .($os->km_entrada ? ', km '.number_format($os->km_entrada, 0, ',', '.') : '').'. OS '.$os->numeroFormatado().': '
            .$servicos->map(fn ($i) => $i->descricao.($i->quantidade != 1 ? ' ('.$i->quantidadeFormatada().')' : ''))->join('; ').'.';

        $aliquota = (float) $e->aliquota_iss;
        $dados = [
            'data_emissao' => now()->toIso8601String(),
            'natureza_operacao' => '1',
            'optante_simples_nacional' => $e->optanteSimples(),
            'prestador' => array_filter([
                'cnpj' => $e->cnpj, 'inscricao_municipal' => $e->inscricao_municipal, 'codigo_municipio' => $e->codigo_municipio,
            ]),
            'servico' => [
                'valor_servicos' => round($valor / 100, 2),
                'discriminacao' => mb_substr($discriminacao, 0, 2000),
                'item_lista_servico' => $e->item_lista_servico,
                'iss_retido' => false,
                'aliquota' => $aliquota,
                'valor_iss' => round($valor * $aliquota / 10000, 2),
            ],
        ];
        if ($tomador = $this->tomador($os)) {
            $dados['tomador'] = $tomador;
        }

        return [$dados, $valor];
    }

    private function tomador(OrdemServico $os): ?array
    {
        $c = $os->cliente;
        if (! $c) {
            return null;
        }
        $doc = Documento::normalizar($c->cpf_cnpj);
        $t = array_filter([
            strlen((string) $doc) === 14 ? 'cnpj' : 'cpf' => $doc ?: null,
            'razao_social' => $c->nome,
            'email' => $c->email,
            'telefone' => preg_replace('/\D/', '', (string) ($c->telefone ?: $c->whatsapp)) ?: null,
        ]);
        if ($c->logradouro && $c->cep) {
            $t['endereco'] = array_filter(['logradouro' => $c->logradouro, 'numero' => $c->numero ?: 'S/N', 'bairro' => $c->bairro, 'uf' => $c->uf, 'cep' => $c->cep]);
        }

        return $t;
    }

    /** NFC-e ou NF-e com as peças aprovadas. */
    private function produtos(OrdemServico $os, Empresa $e, string $tipo, bool $real): array
    {
        $pecas = $os->itens->where('situacao', 'aprovado')->where('tipo', 'peca')->values();
        $valor = $os->valorPecas();
        if ($pecas->isEmpty() || $valor <= 0) {
            throw new DomainException('Esta OS não tem peças aprovadas.');
        }
        if ($real) {
            $this->exigirEmpresa($e, ['cnpj' => 'CNPJ', 'inscricao_estadual' => 'inscrição estadual']);
        }
        $c = $os->cliente;
        $doc = Documento::normalizar($c?->cpf_cnpj);
        if ($tipo === 'nfe' && (! $c || ! $doc || ! $c->logradouro || ! $c->cep || ! $c->cidade || ! $c->uf)) {
            throw new DomainException('Para NF-e, o cliente precisa ter CPF/CNPJ e endereço completo (rua, número, bairro, cidade, UF e CEP).');
        }

        // Desconto da OS distribuído nas peças, proporcional
        $brutoPecas = (int) $pecas->sum('total_centavos');
        $descontoTotal = $brutoPecas - $valor;
        $itens = [];
        $descontoUsado = 0;
        foreach ($pecas as $n => $i) {
            $ncm = $i->produto?->ncm;
            if (! $ncm) {
                throw new DomainException('A peça "'.$i->descricao.'" está sem NCM. Use uma peça do estoque com NCM cadastrado.');
            }
            $desc = $n === $pecas->count() - 1 ? $descontoTotal - $descontoUsado : (int) round($descontoTotal * $i->total_centavos / max(1, $brutoPecas));
            $descontoUsado += $desc;
            $unid = $i->produto?->unidade ?: 'UN';
            $itens[] = array_filter([
                'numero_item' => $n + 1,
                'codigo_produto' => $i->produto?->codigo ?: (string) $i->produto_id,
                'descricao' => mb_substr($i->descricao, 0, 120),
                'codigo_ncm' => $ncm,
                'cfop' => $e->cfop_padrao,
                'unidade_comercial' => $unid,
                'quantidade_comercial' => (float) $i->quantidade,
                'valor_unitario_comercial' => round($i->valor_unitario_centavos / 100, 2),
                'unidade_tributavel' => $unid,
                'quantidade_tributavel' => (float) $i->quantidade,
                'valor_unitario_tributavel' => round($i->valor_unitario_centavos / 100, 2),
                'valor_bruto' => round($i->total_centavos / 100, 2),
                'valor_desconto' => $desc > 0 ? round($desc / 100, 2) : null,
                'icms_origem' => config('fiscal.icms_origem'),
                'icms_situacao_tributaria' => $e->optanteSimples() ? $e->csosn_padrao : '00',
                'pis_situacao_tributaria' => config('fiscal.pis_cst'),
                'cofins_situacao_tributaria' => config('fiscal.cofins_cst'),
            ], fn ($v) => $v !== null);
        }

        $dados = [
            'cnpj_emitente' => $e->cnpj,
            'data_emissao' => now()->toIso8601String(),
            'natureza_operacao' => 'Venda de mercadoria',
            'presenca_comprador' => '1',
            'modalidade_frete' => '9',
            'local_destino' => '1',
            'items' => $itens,
        ];

        if ($tipo === 'nfce') {
            $dados['formas_pagamento'] = $this->formasPagamento($os, $valor);
            if ($doc && strlen($doc) === 11) {
                $dados['cpf_destinatario'] = $doc;
            } elseif ($doc && strlen($doc) === 14) {
                $dados['cnpj_destinatario'] = $doc;
            }
        } else {
            $dados += [
                'tipo_documento' => '1',
                'finalidade_emissao' => '1',
                'consumidor_final' => '1',
                'nome_destinatario' => $c->nome,
                strlen($doc) === 14 ? 'cnpj_destinatario' : 'cpf_destinatario' => $doc,
                'indicador_inscricao_estadual_destinatario' => $c->ie ? '1' : '9',
                'logradouro_destinatario' => $c->logradouro,
                'numero_destinatario' => $c->numero ?: 'S/N',
                'bairro_destinatario' => $c->bairro,
                'municipio_destinatario' => $c->cidade,
                'uf_destinatario' => $c->uf,
                'cep_destinatario' => $c->cep,
            ];
            if ($c->ie) {
                $dados['inscricao_estadual_destinatario'] = $c->ie;
            }
        }

        return [$dados, $valor];
    }

    /** Códigos da SEFAZ: 01 dinheiro, 03 crédito, 04 débito, 15 boleto, 17 Pix, 05 crédito loja, 99 outros. */
    private function formasPagamento(OrdemServico $os, int $valor): array
    {
        $codigo = ['dinheiro' => '01', 'credito' => '03', 'debito' => '04', 'boleto' => '15', 'pix' => '17', 'prazo' => '05', 'transferencia' => '99'];
        $porForma = $os->contasReceber->groupBy('forma')->map->sum('valor_centavos');
        $total = max(1, (int) $porForma->sum());
        $formas = [];
        $usado = 0;
        $chaves = $porForma->keys()->values();
        foreach ($chaves as $n => $forma) {
            $parte = $n === $chaves->count() - 1 ? $valor - $usado : (int) round($valor * $porForma[$forma] / $total);
            $usado += $parte;
            if ($parte > 0) {
                $formas[] = ['forma_pagamento' => $codigo[$forma] ?? '99', 'valor_pagamento' => round($parte / 100, 2)];
            }
        }

        return $formas ?: [['forma_pagamento' => '99', 'valor_pagamento' => round($valor / 100, 2)]];
    }
}
