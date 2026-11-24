<?php

namespace App\Support;

/** Leitura de planilha CSV enviada pela oficina. */
final class PlanilhaCsv
{
    /** Lê CSV com ; ou , e em UTF-8 ou no padrão do Excel brasileiro (Windows-1252). */
    public static function ler(string $arquivo): array
    {
        $conteudo = (string) file_get_contents($arquivo);
        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);
        if (! mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
        }
        $primeira = strtok($conteudo, "\n");
        $sep = substr_count((string) $primeira, ';') >= substr_count((string) $primeira, ',') ? ';' : ',';
        $linhas = [];
        $h = fopen('php://memory', 'r+');
        fwrite($h, $conteudo);
        rewind($h);
        while (($l = fgetcsv($h, 0, $sep, '"', '')) !== false) {
            $linhas[] = array_map(fn ($v) => ltrim((string) $v, "'"), $l);
        }
        fclose($h);

        return $linhas;
    }
}
