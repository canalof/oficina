<?php

namespace App\Services\Estoque;

use App\Support\Dinheiro;
use InvalidArgumentException;
use SimpleXMLElement;

/**
 * Lê o XML da NF-e de compra (o arquivo que o fornecedor manda por e-mail).
 * Aceita tanto o XML completo (nfeProc) quanto só a NFe.
 */
class LeitorXmlNfe
{
    private const NS = 'http://www.portalfiscal.inf.br/nfe';

    public function ler(string $conteudo): array
    {
        $conteudo = trim($conteudo);
        if ($conteudo === '' || ! str_contains($conteudo, '<')) {
            throw new InvalidArgumentException('O arquivo não é um XML.');
        }
        // Sem acesso à rede e sem entidades externas
        $anterior = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($conteudo, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_use_internal_errors($anterior);
        if (! $xml) {
            throw new InvalidArgumentException('Não foi possível ler o XML. Confira se é o arquivo da nota (termina em .xml).');
        }

        $xml->registerXPathNamespace('n', self::NS);
        $inf = $xml->xpath('//n:infNFe')[0] ?? null;
        if (! $inf) {
            throw new InvalidArgumentException('Este XML não é de uma NF-e.');
        }
        $inf->registerXPathNamespace('n', self::NS);
        $um = fn (string $caminho) => ($r = $inf->xpath($caminho)) ? trim((string) $r[0]) : null;

        $chave = preg_replace('/\D/', '', (string) $inf['Id']);
        $itens = [];
        foreach ($inf->xpath('n:det') as $det) {
            $det->registerXPathNamespace('n', self::NS);
            $p = fn (string $c) => ($r = $det->xpath('n:prod/n:'.$c)) ? trim((string) $r[0]) : null;
            $qtd = (float) $p('qCom');
            $ean = preg_replace('/\D/', '', (string) $p('cEAN'));
            $itens[] = [
                'codigo_fornecedor' => $p('cProd'),
                'codigo_barras' => in_array($ean, ['', 'SEMGTIN'], true) || strlen($ean) < 8 ? null : $ean,
                'descricao' => mb_substr((string) $p('xProd'), 0, 200),
                'ncm' => preg_replace('/\D/', '', (string) $p('NCM')) ?: null,
                'unidade' => mb_strtoupper(mb_substr((string) $p('uCom'), 0, 6)) ?: 'UN',
                'quantidade' => $qtd,
                'custo_unitario_centavos' => (int) round(((float) $p('vUnCom')) * 100),
                'total_centavos' => (int) round(((float) $p('vProd')) * 100),
            ];
        }
        if (! $itens) {
            throw new InvalidArgumentException('A nota não tem produtos.');
        }

        $duplicatas = [];
        foreach ($inf->xpath('n:cobr/n:dup') as $dup) {
            $dup->registerXPathNamespace('n', self::NS);
            $duplicatas[] = [
                'numero' => trim((string) ($dup->xpath('n:nDup')[0] ?? '')),
                'vencimento' => trim((string) ($dup->xpath('n:dVenc')[0] ?? '')),
                'valor_centavos' => Dinheiro::paraCentavos((string) ($dup->xpath('n:vDup')[0] ?? '0')),
            ];
        }

        $emissao = $um('n:ide/n:dhEmi') ?: $um('n:ide/n:dEmi');

        return [
            'chave' => strlen($chave) === 44 ? $chave : null,
            'numero' => $um('n:ide/n:nNF'),
            'serie' => $um('n:ide/n:serie'),
            'emissao' => $emissao ? substr($emissao, 0, 10) : null,
            'total_centavos' => (int) round(((float) $um('n:total/n:ICMSTot/n:vNF')) * 100),
            'fornecedor' => [
                'cpf_cnpj' => $um('n:emit/n:CNPJ') ?: $um('n:emit/n:CPF'),
                'nome' => $um('n:emit/n:xFant') ?: $um('n:emit/n:xNome'),
                'telefone' => $um('n:emit/n:enderEmit/n:fone'),
                'cidade' => $um('n:emit/n:enderEmit/n:xMun'),
                'uf' => $um('n:emit/n:enderEmit/n:UF'),
            ],
            'itens' => $itens,
            'duplicatas' => $duplicatas,
        ];
    }
}
