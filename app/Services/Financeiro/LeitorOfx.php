<?php

namespace App\Services\Financeiro;

use InvalidArgumentException;

/** Parser limitado a STMTTRN de OFX 1.x SGML e OFX 2.x XML, sem entidades externas. */
final class LeitorOfx
{
    public function ler(string $conteudo): array
    {
        if (strlen($conteudo) > 2_097_152 || str_contains(strtoupper($conteudo), '<!DOCTYPE') || str_contains(strtoupper($conteudo), '<!ENTITY')) {
            throw new InvalidArgumentException('OFX excede o limite ou contém declarações não permitidas.');
        }
        if (! preg_match('/<STMTTRN(?:\s[^>]*)?>(.*?)<\/STMTTRN>/is', $conteudo)) {
            throw new InvalidArgumentException('Nenhuma transação STMTTRN encontrada.');
        }
        preg_match_all('/<STMTTRN(?:\s[^>]*)?>(.*?)<\/STMTTRN>/is', $conteudo, $blocos);
        if (count($blocos[1]) > 5000) {
            throw new InvalidArgumentException('Limite de 5.000 transações por extrato.');
        }
        $resultado = [];
        foreach ($blocos[1] as $bloco) {
            $campo = static function (string $nome) use ($bloco): ?string {
                if (! preg_match('~<'.preg_quote($nome, '~').'(?:\s[^>]*)?>\s*([^<\r\n]+)~i', $bloco, $m)) return null;
                return trim(html_entity_decode($m[1], ENT_QUOTES | ENT_XML1, 'UTF-8'));
            };
            $id = $campo('FITID');
            $data = $campo('DTPOSTED');
            $valor = $campo('TRNAMT');
            $descricao = $campo('MEMO') ?: $campo('NAME') ?: 'Movimento OFX';
            if (! $id || strlen($id) > 190 || ! $data || ! preg_match('/^(\d{4})(\d{2})(\d{2})/', $data, $d)
                || ! checkdate((int)$d[2], (int)$d[3], (int)$d[1])) {
                throw new InvalidArgumentException('FITID ou data inválida no extrato.');
            }
            if (! $valor || ! preg_match('/^([+-]?)(\d{1,11})(?:\.(\d{1,2}))?$/D', $valor, $v)) {
                throw new InvalidArgumentException('Valor monetário OFX inválido.');
            }
            $centavos = (int)$v[2] * 100 + (int)str_pad($v[3] ?? '', 2, '0');
            if (($v[1] ?? '') === '-') $centavos *= -1;
            if ($centavos === 0) throw new InvalidArgumentException('Movimento OFX com valor zero.');
            $resultado[] = [
                'data_movimento' => "$d[1]-$d[2]-$d[3]",
                'descricao' => substr($descricao, 0, 255),
                'valor_centavos' => $centavos,
                'referencia_externa' => $id,
            ];
        }
        return $resultado;
    }
}
