<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Fabricante;
use App\Models\Fornecedor;
use App\Models\Produto;
use App\Models\Veiculo;
use App\Support\Dinheiro;
use App\Support\Documento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Importar e exportar cadastros em planilha (CSV, abre no Excel).
 * A importação atualiza quem já existe (pelo CPF/CNPJ ou pelo código) e cria os novos.
 */
class ImportacaoController extends Controller
{
    private const COLUNAS = [
        'clientes' => ['nome', 'cpf_cnpj', 'telefone', 'whatsapp', 'email', 'cep', 'logradouro', 'numero', 'bairro', 'cidade', 'uf', 'observacoes'],
        'produtos' => ['codigo', 'codigo_barras', 'referencia_fabricante', 'nome', 'fabricante', 'categoria', 'unidade', 'ncm', 'localizacao', 'custo', 'preco', 'estoque_minimo', 'estoque_atual'],
    ];

    private const LIMITE = 5000;

    private function permissao(string $tipo, bool $editar): string
    {
        return match ($tipo) {
            // exportar entrega a base inteira (com CPF/CNPJ): exige permissão de edição, não só de consulta
            'clientes' => 'clientes.editar',
            'veiculos' => 'veiculos.editar',
            'fornecedores' => 'compras.lancar',
            default => 'estoque.editar',
        };
    }

    public function form(string $tipo)
    {
        $this->authorize($this->permissao($tipo, true));

        return view('importar', ['tipo' => $tipo, 'colunas' => self::COLUNAS[$tipo], 'resultado' => session('resultado')]);
    }

    public function modelo(string $tipo)
    {
        $this->authorize($this->permissao($tipo, true));
        $exemplo = $tipo === 'clientes'
            ? ['Maria da Silva', '529.982.247-25', '(19) 3333-4444', '(19) 99999-8888', 'maria@email.com', '13170-000', 'Rua das Flores', '120', 'Centro', 'Sumaré', 'SP', '']
            : ['FO-123', '7891234567895', 'PSL55', 'Filtro de óleo PSL55', 'Tecfil', 'Filtros', 'UN', '84212300', 'A2', '15,00', '35,00', '5', '10'];

        return $this->csv("modelo-{$tipo}.csv", self::COLUNAS[$tipo], [$exemplo]);
    }

    public function importar(Request $request, string $tipo)
    {
        $this->authorize($this->permissao($tipo, true));
        $request->validate(['arquivo' => ['required', 'file', 'max:5120']], ['arquivo.required' => 'Escolha o arquivo da planilha (.csv).']);

        $linhas = $this->ler($request->file('arquivo')->getRealPath());
        if (count($linhas) < 2) {
            return back()->with('erro', 'A planilha está vazia ou não é CSV. No Excel, use "Salvar como" → "CSV (separado por vírgulas)".');
        }
        $cab = array_map(fn ($c) => strtolower(trim(\Illuminate\Support\Str::ascii($c))), array_shift($linhas));
        if (! in_array('nome', $cab, true)) {
            return back()->with('erro', 'A primeira linha precisa ter os nomes das colunas (baixe o modelo).');
        }
        if (count($linhas) > self::LIMITE) {
            return back()->with('erro', 'Máximo de '.self::LIMITE.' linhas por arquivo. Divida a planilha.');
        }

        $r = ['criados' => 0, 'atualizados' => 0, 'erros' => []];
        foreach ($linhas as $n => $valores) {
            if (! array_filter($valores, fn ($v) => trim((string) $v) !== '')) {
                continue;
            }
            $l = array_combine($cab, array_pad(array_slice($valores, 0, count($cab)), count($cab), ''));
            try {
                DB::transaction(function () use ($tipo, $l, &$r) {
                    $tipo === 'clientes' ? $this->cliente($l, $r) : $this->produto($l, $r);
                });
            } catch (\InvalidArgumentException $e) {
                $r['erros'][] = 'Linha '.($n + 2).': '.$e->getMessage();
            }
        }

        return back()->with('resultado', $r);
    }

    private function cliente(array $l, array &$r): void
    {
        $nome = trim($l['nome'] ?? '');
        if ($nome === '') {
            throw new \InvalidArgumentException('sem nome');
        }
        $doc = Documento::normalizar($l['cpf_cnpj'] ?? '') ?: null;
        if ($doc && ! Documento::valido($doc)) {
            throw new \InvalidArgumentException("CPF/CNPJ inválido ({$l['cpf_cnpj']})");
        }
        $dados = array_filter([
            'nome' => mb_substr($nome, 0, 255), 'cpf_cnpj' => $doc, 'tipo_pessoa' => $doc && strlen($doc) === 14 ? 'PJ' : 'PF',
            'telefone' => trim($l['telefone'] ?? '') ?: null, 'whatsapp' => trim($l['whatsapp'] ?? '') ?: null,
            'email' => strtolower(trim($l['email'] ?? '')) ?: null, 'cep' => preg_replace('/\D/', '', $l['cep'] ?? '') ?: null,
            'logradouro' => trim($l['logradouro'] ?? '') ?: null, 'numero' => trim($l['numero'] ?? '') ?: null,
            'bairro' => trim($l['bairro'] ?? '') ?: null, 'cidade' => trim($l['cidade'] ?? '') ?: null,
            'uf' => strtoupper(substr(trim($l['uf'] ?? ''), 0, 2)) ?: null, 'observacoes' => trim($l['observacoes'] ?? '') ?: null,
        ], fn ($v) => $v !== null);
        if (isset($dados['email']) && ! filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            unset($dados['email']);
        }
        $existente = $doc ? Cliente::where('cpf_cnpj', $doc)->first() : null;
        if ($existente) {
            $existente->update($dados);
            $r['atualizados']++;
        } else {
            Cliente::create($dados);
            $r['criados']++;
        }
    }

    private function produto(array $l, array &$r): void
    {
        $nome = trim($l['nome'] ?? '');
        if ($nome === '') {
            throw new \InvalidArgumentException('sem nome');
        }
        $unidade = strtoupper(trim($l['unidade'] ?? '')) ?: 'UN';
        if (! isset(Produto::UNIDADES[$unidade])) {
            throw new \InvalidArgumentException("unidade \"{$unidade}\" não reconhecida (use ".implode(', ', array_keys(Produto::UNIDADES)).')');
        }
        $num = fn ($v) => (float) str_replace(',', '.', str_replace('.', '', (string) $v)) ?: (float) str_replace(',', '.', (string) $v);
        $ref = fn (string $classe, ?string $n) => ($n = trim((string) $n)) !== '' ? $classe::firstOrCreate(['nome' => mb_substr($n, 0, 80)], ['ativo' => true])->id : null;
        $dados = array_filter([
            'nome' => mb_substr($nome, 0, 150), 'codigo' => trim($l['codigo'] ?? '') ?: null,
            'codigo_barras' => preg_replace('/\D/', '', $l['codigo_barras'] ?? '') ?: null,
            'referencia_fabricante' => trim($l['referencia_fabricante'] ?? '') ?: null,
            'fabricante_id' => $ref(Fabricante::class, $l['fabricante'] ?? null), 'categoria_id' => $ref(Categoria::class, $l['categoria'] ?? null),
            'unidade' => $unidade, 'ncm' => preg_replace('/\D/', '', $l['ncm'] ?? '') ?: null, 'localizacao' => trim($l['localizacao'] ?? '') ?: null,
            'preco_centavos' => trim($l['preco'] ?? '') !== '' ? Dinheiro::paraCentavos($l['preco']) : null,
            'estoque_minimo' => trim($l['estoque_minimo'] ?? '') !== '' ? str_replace(',', '.', $l['estoque_minimo']) : null,
        ], fn ($v) => $v !== null);
        if (isset($dados['ncm']) && strlen($dados['ncm']) !== 8) {
            throw new \InvalidArgumentException("NCM precisa ter 8 números ({$l['ncm']})");
        }
        $custo = trim($l['custo'] ?? '') !== '' ? Dinheiro::paraCentavos($l['custo']) : null;

        $existente = null;
        if (! empty($dados['codigo'])) {
            $existente = Produto::where('codigo', $dados['codigo'])->first();
        }
        if (! $existente && ! empty($dados['codigo_barras'])) {
            $existente = Produto::where('codigo_barras', $dados['codigo_barras'])->first();
        }
        if ($existente) {
            $existente->update($dados); // estoque de quem já existe não muda pela planilha (use a contagem)
            $r['atualizados']++;

            return;
        }
        $p = Produto::create($dados + ['custo_centavos' => $custo ?? 0]);
        $qtd = trim($l['estoque_atual'] ?? '') !== '' ? (float) str_replace(',', '.', $l['estoque_atual']) : 0;
        if ($qtd > 0) {
            $p->movimentar($qtd, 'entrada', 'manual', null, $custo, 'Estoque inicial (importação de planilha)');
        }
        $r['criados']++;
    }

    public function exportar(string $tipo)
    {
        $this->authorize($this->permissao($tipo, false));
        $r = fn (?int $c) => $c === null ? '' : number_format($c / 100, 2, ',', '');
        $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', ''), '0'), ',');
        [$cab, $linhas] = match ($tipo) {
            'clientes' => [self::COLUNAS['clientes'], Cliente::orderBy('nome')->cursor()->map(fn ($c) => [
                $c->nome, $c->cpf_cnpj ? Documento::formatar($c->cpf_cnpj) : '', $c->telefone, $c->whatsapp, $c->email, $c->cep, $c->logradouro, $c->numero, $c->bairro, $c->cidade, $c->uf, $c->observacoes])],
            'produtos' => [self::COLUNAS['produtos'], Produto::with(['fabricante', 'categoria'])->orderBy('nome')->cursor()->map(fn ($p) => [
                $p->codigo, $p->codigo_barras, $p->referencia_fabricante, $p->nome, $p->marcaNome(), $p->categoria?->nome, $p->unidade, $p->ncm, $p->localizacao,
                $r($p->custo_centavos), $r($p->preco_centavos), $q($p->estoque_minimo), $q($p->estoque)])],
            'veiculos' => [['placa', 'marca', 'modelo', 'versao', 'motor', 'ano_fabricacao', 'ano_modelo', 'cor', 'combustivel', 'chassi', 'renavam', 'km_atual', 'dono'],
                Veiculo::with('cliente')->orderBy('placa')->cursor()->map(fn ($v) => [$v->placaFormatada(), $v->marca, $v->modelo, $v->versao, $v->motor, $v->ano_fabricacao, $v->ano_modelo, $v->cor, $v->combustivel, $v->chassi, $v->renavam, $v->km_atual, $v->cliente?->nome])],
            'fornecedores' => [['nome', 'cpf_cnpj', 'contato', 'telefone', 'email', 'cidade', 'uf'],
                Fornecedor::orderBy('nome')->cursor()->map(fn ($f) => [$f->nome, $f->documentoFormatado(), $f->contato, $f->telefone, $f->email, $f->cidade, $f->uf])],
        };

        return $this->csv("torque-{$tipo}-".now()->format('Y-m-d').'.csv', $cab, $linhas);
    }

    private function csv(string $nome, array $cab, iterable $linhas)
    {
        return response()->streamDownload(function () use ($cab, $linhas) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, $cab, ';', '"', '');
            foreach ($linhas as $l) {
                fputcsv($f, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, $l), ';', '"', '');
            }
            fclose($f);
        }, $nome, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Lê CSV com ; ou , e em UTF-8 ou no padrão do Excel brasileiro (Windows-1252). */
    private function ler(string $arquivo): array
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
