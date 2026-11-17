<?php

namespace App\Http\Controllers;

use App\Models\ContaBancaria;
use App\Models\MovimentoBancario;
use App\Services\Pix\PixEstatico;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BancoController extends Controller
{
    public function configurarPix(Request $request, ContaBancaria $conta)
    {
        $this->authorize('financeiro.editar');
        $dados = $request->validate([
            'pix_tipo' => ['required', Rule::in(['cpf', 'cnpj', 'telefone', 'email', 'aleatoria'])],
            'pix_chave' => ['required', 'string', 'max:77'],
            'pix_recebedor' => ['required', 'string', 'max:100'],
            'pix_cidade' => ['required', 'string', 'max:100'],
        ]);
        $chave = PixEstatico::chave($dados['pix_tipo'], $dados['pix_chave']);
        $recebedor = PixEstatico::texto($dados['pix_recebedor']);
        $cidade = PixEstatico::texto($dados['pix_cidade']);
        if ($chave === null) {
            throw ValidationException::withMessages(['pix_chave' => 'Informe uma chave Pix válida para o tipo selecionado.']);
        }
        if ($recebedor === '' || strlen($recebedor) > 25 || $cidade === '' || strlen($cidade) > 15) {
            throw ValidationException::withMessages(['pix_recebedor' => 'Use até 25 caracteres no nome do recebedor e até 15 na cidade.']);
        }
        $conta->update([
            'pix_tipo' => $dados['pix_tipo'],
            'pix_chave' => $chave,
            'pix_recebedor' => $recebedor,
            'pix_cidade' => $cidade,
        ]);

        return back()->with('ok', 'Pix manual configurado para a conta.');
    }

    public function index()
    {
        $this->authorize('financeiro.ver');
        $contas = ContaBancaria::orderBy('apelido')->get();
        $movimentos = MovimentoBancario::with('contaBancaria')->latest('data_movimento')->latest('id')->limit(100)->get();
        $sugestoes = app(\App\Services\Financeiro\SugestoesConciliacao::class)->paraMovimentos($movimentos);
        return view('bancos.index', compact('contas', 'movimentos', 'sugestoes'));
    }

    public function store(Request $request)
    {
        $this->authorize('financeiro.editar');
        $dados = $request->validate([
            'apelido' => ['required','string','max:100'],
            'banco_codigo' => ['nullable','string','max:10'],
            'banco_nome' => ['required','string','max:100'],
            'agencia' => ['nullable','string','max:20'],
            'numero_conta' => ['nullable','string','max:40'],
            'tipo' => ['required',Rule::in(['corrente','poupanca','pagamento'])],
        ]);
        $dados['saldo_inicial_centavos'] = 0;
        ContaBancaria::create($dados);
        return back()->with('ok', 'Conta bancária cadastrada.');
    }

    public function movimento(Request $request, ContaBancaria $conta)
    {
        $this->authorize('financeiro.editar');
        abort_unless($conta->ativa, 422);
        $dados = $request->validate([
            'data_movimento' => ['required','date'],
            'valor_centavos' => ['required','integer','not_in:0','between:-99999999999,99999999999'],
            'descricao' => ['required','string','max:255'],
        ]);
        $conta->movimentos()->create($dados + [
            'origem' => 'manual', 'status_conciliacao' => 'pendente',
            'criado_por' => $request->user()->id,
        ]);
        return back()->with('ok', 'Movimento registrado.');
    }

    /**
     * Conciliação documental: vincula extrato a um título JÁ liquidado.
     * Não realiza pagamento, recebimento nem altera saldo financeiro.
     */
    public function conciliar(Request $request, MovimentoBancario $movimento)
    {
        $this->authorize('financeiro.editar');
        $dados = $request->validate([
            'tipo' => ['required', Rule::in(['receber', 'pagar'])],
            'titulo_id' => ['required', 'integer', 'min:1'],
        ]);
        DB::transaction(function () use ($movimento, $dados) {
            $movimento = MovimentoBancario::whereKey($movimento->id)->lockForUpdate()->firstOrFail();
            abort_unless($movimento->status_conciliacao === 'pendente', 409, 'Movimento já conciliado.');
            $credito = $movimento->valor_centavos > 0;
            abort_unless($credito === ($dados['tipo'] === 'receber'), 422, 'Tipo incompatível com o sinal do extrato.');
            $classe = $credito ? \App\Models\ContaReceber::class : \App\Models\ContaPagar::class;
            $titulo = $classe::whereKey($dados['titulo_id'])->lockForUpdate()->firstOrFail();
            $liquidado = $credito ? 'recebida' : 'paga';
            abort_unless($titulo->status === $liquidado, 422, 'O título precisa estar liquidado antes da conciliação.');
            abort_unless((int) $titulo->valor_centavos === abs((int) $movimento->valor_centavos), 422, 'Valor do título diferente do extrato.');
            $campo = $credito ? 'conta_receber_id' : 'conta_pagar_id';
            abort_if(MovimentoBancario::where($campo, $titulo->id)->where('status_conciliacao', 'conciliado')->exists(), 409, 'Título já conciliado.');
            $movimento->update([
                $campo => $titulo->id,
                'status_conciliacao' => 'conciliado',
            ]);
        });
        return back()->with('ok', 'Conciliação documental registrada, sem alterar o título.');
    }

    public function desfazerConciliacao(MovimentoBancario $movimento)
    {
        $this->authorize('financeiro.editar');
        DB::transaction(function () use ($movimento) {
            $movimento = MovimentoBancario::whereKey($movimento->id)->lockForUpdate()->firstOrFail();
            abort_unless($movimento->status_conciliacao === 'conciliado', 409);
            $movimento->update([
                'status_conciliacao' => 'pendente',
                'conta_receber_id' => null,
                'conta_pagar_id' => null,
            ]);
        });
        return back()->with('ok', 'Vínculo de conciliação desfeito; títulos e saldos preservados.');
    }

    public function importarOfx(Request $request, ContaBancaria $conta, \App\Services\Financeiro\LeitorOfx $leitor)
    {
        $this->authorize('financeiro.editar');
        abort_unless($conta->ativa, 422);
        $request->validate(['arquivo' => ['required', 'file', 'max:2048']]);
        try {
            $linhas = $leitor->ler(file_get_contents($request->file('arquivo')->getRealPath()));
        } catch (\InvalidArgumentException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(['arquivo' => $e->getMessage()]);
        }
        $inseridos = DB::transaction(function () use ($conta, $linhas, $request) {
            $conta = ContaBancaria::whereKey($conta->id)->lockForUpdate()->firstOrFail();
            $total = 0;
            foreach ($linhas as $linha) {
                $hash = hash('sha256', $conta->id.'|'.$linha['referencia_externa']);
                if ($conta->movimentos()->where('hash_importacao', $hash)->exists()) continue;
                $conta->movimentos()->create($linha + [
                    'hash_importacao' => $hash, 'origem' => 'ofx',
                    'status_conciliacao' => 'pendente', 'criado_por' => $request->user()->id,
                ]);
                $total++;
            }
            return $total;
        });
        return back()->with('ok', "$inseridos movimentos OFX importados; duplicados ignorados.");
    }

    public function importarCsv(Request $request, ContaBancaria $conta)
    {
        $this->authorize('financeiro.editar');
        abort_unless($conta->ativa, 422);
        $request->validate(['arquivo' => ['required','file','max:2048','mimes:csv,txt']]);
        $path = $request->file('arquivo')->getRealPath();
        $handle = fopen($path, 'rb');
        abort_unless($handle !== false, 422);
        $linhas = [];
        try {
            $header = fgetcsv($handle, 4096, ';');
            abort_unless($header === ['data','descricao','valor_centavos','referencia'], 422, 'Cabeçalho CSV inválido.');
            while (($cols = fgetcsv($handle, 4096, ';')) !== false) {
                abort_if(count($linhas) >= 5000, 422, 'Limite de 5.000 movimentos por arquivo.');
                abort_unless(count($cols) === 4, 422, 'Linha CSV inválida.');
                [$data,$descricao,$valor,$referencia] = $cols;
                abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/D', $data) &&
                    \DateTimeImmutable::createFromFormat('!Y-m-d', $data)?->format('Y-m-d') === $data, 422, 'Data inválida.');
                abort_unless($descricao !== '' && mb_strlen($descricao) <= 255, 422, 'Descrição inválida.');
                abort_unless(preg_match('/^-?\d{1,11}$/D', $valor) && (int)$valor !== 0, 422, 'Valor inválido.');
                abort_unless($referencia !== '' && mb_strlen($referencia) <= 190, 422, 'Referência obrigatória para evitar duplicidade.');
                $hash = hash('sha256', implode('|', [$conta->id, $referencia]));
                $linhas[] = compact('data','descricao','valor','referencia','hash');
            }
        } finally {
            fclose($handle);
        }
        $inseridos = DB::transaction(function () use ($conta, $linhas, $request) {
            $conta = ContaBancaria::whereKey($conta->id)->lockForUpdate()->firstOrFail();
            abort_unless($conta->ativa, 422);
            $total = 0;
            foreach ($linhas as $linha) {
                $existe = MovimentoBancario::where('conta_bancaria_id', $conta->id)->where('hash_importacao', $linha['hash'])->exists();
                if ($existe) continue;
                $conta->movimentos()->create([
                    'data_movimento' => $linha['data'], 'descricao' => $linha['descricao'],
                    'valor_centavos' => (int)$linha['valor'], 'referencia_externa' => $linha['referencia'],
                    'hash_importacao' => $linha['hash'], 'origem' => 'csv',
                    'status_conciliacao' => 'pendente', 'criado_por' => $request->user()->id,
                ]);
                $total++;
            }
            return $total;
        });
        return back()->with('ok', "$inseridos movimentos importados; duplicados ignorados.");
    }
}
