<?php

namespace App\Support;

use App\Models\Cliente;
use App\Models\Fornecedor;
use App\Models\Produto;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Procura cadastros parecidos antes de criar um novo (nome igual, mesmo telefone, mesmo código). */
class Duplicados
{
    public static function nome(?string $v): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii((string) $v))));
    }

    public static function clientes(array $dados, ?int $ignorar = null): Collection
    {
        $fones = collect([$dados['telefone'] ?? null, $dados['whatsapp'] ?? null])
            ->map(fn ($f) => preg_replace('/\D/', '', (string) $f))->filter(fn ($f) => strlen($f) >= 10)->unique();
        $nome = self::nome($dados['nome'] ?? '');

        return Cliente::when($ignorar, fn ($q) => $q->where('id', '<>', $ignorar))->get(['id', 'nome', 'telefone', 'whatsapp', 'cpf_cnpj'])
            ->filter(function ($c) use ($fones, $nome) {
                if ($nome !== '' && self::nome($c->nome) === $nome) {
                    return true;
                }
                foreach ([$c->telefone, $c->whatsapp] as $f) {
                    if ($fones->contains(preg_replace('/\D/', '', (string) $f))) {
                        return true;
                    }
                }

                return false;
            })->take(5)->values();
    }

    public static function produtos(array $dados, ?int $ignorar = null): Collection
    {
        $nome = self::nome($dados['nome'] ?? '');

        return Produto::when($ignorar, fn ($q) => $q->where('id', '<>', $ignorar))
            ->where(function ($q) use ($dados) {
                $q->whereRaw('1 = 0');
                if (! empty($dados['codigo_barras'])) {
                    $q->orWhere('codigo_barras', $dados['codigo_barras']);
                }
                if (! empty($dados['referencia_fabricante'])) {
                    $q->orWhere('referencia_fabricante', $dados['referencia_fabricante']);
                }
            })->get(['id', 'nome'])
            ->merge(Produto::when($ignorar, fn ($q) => $q->where('id', '<>', $ignorar))->get(['id', 'nome'])->filter(fn ($p) => $nome !== '' && self::nome($p->nome) === $nome))
            ->unique('id')->take(5)->values();
    }

    public static function fornecedores(array $dados, ?int $ignorar = null): Collection
    {
        $nome = self::nome($dados['nome'] ?? '');

        return Fornecedor::when($ignorar, fn ($q) => $q->where('id', '<>', $ignorar))->get(['id', 'nome'])
            ->filter(fn ($f) => $nome !== '' && self::nome($f->nome) === $nome)->take(5)->values();
    }
}
