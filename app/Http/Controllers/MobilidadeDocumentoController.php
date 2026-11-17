<?php

namespace App\Http\Controllers;

use App\Models\MobilidadeCondutor;
use App\Models\MobilidadeDocumento;
use App\Models\MobilidadeFoto;
use App\Models\MobilidadeModelo;
use App\Models\MobilidadeReserva;
use App\Models\MobilidadeRetirada;
use App\Models\OrdemServico;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class MobilidadeDocumentoController extends Controller
{
    public const TIPOS = [
        'autorizacao' => 'Autorização de retirada', 'entrega' => 'Termo de entrega',
        'carro_reserva' => 'Termo de carro reserva', 'locacao' => 'Contrato de locação',
        'devolucao' => 'Termo de devolução', 'vistoria' => 'Registro de vistoria',
    ];

    public function index()
    {
        $this->authorize('mobilidade.ver');

        return response()->view('mobilidade.documentos', [
            'tipos' => self::TIPOS,
            'modelos' => MobilidadeModelo::get()->keyBy('tipo'),
            'documentos' => MobilidadeDocumento::latest('id')->paginate(20),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function modelo(Request $request, string $tipo)
    {
        $this->authorize('mobilidade.excecao');
        abort_unless(isset(self::TIPOS[$tipo]), 404);
        $dados = $request->validate(['texto' => ['required', 'string', 'max:12000']]);
        MobilidadeModelo::updateOrCreate(['tipo' => $tipo], $dados);

        return back()->with('ok', 'Modelo de documento salvo.');
    }

    public function gerar(Request $request)
    {
        $this->authorize('mobilidade.editar');
        $dados = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(self::TIPOS))],
            'reserva_id' => ['nullable', 'integer', 'required_without:retirada_id', 'prohibits:retirada_id'],
            'retirada_id' => ['nullable', 'integer', 'required_without:reserva_id', 'prohibits:reserva_id'],
        ]);
        $reserva = empty($dados['reserva_id']) ? null : MobilidadeReserva::findOrFail($dados['reserva_id']);
        $retirada = empty($dados['retirada_id']) ? null : MobilidadeRetirada::findOrFail($dados['retirada_id']);
        $tipo = $dados['tipo'];
        abort_unless($retirada
            ? ($tipo === 'autorizacao' || ($tipo === 'entrega' && $retirada->entregue_em))
            : match ($tipo) {
                'carro_reserva' => $reserva->tipo === 'reserva',
                'locacao' => in_array($reserva->tipo, ['locacao', 'parceira'], true),
                'vistoria' => $reserva->entrega !== null,
                'devolucao' => $reserva->devolucao !== null,
                default => false,
            }, 422, 'Documento incompatível com a etapa do atendimento.');
        $origem = $retirada ?? $reserva;
        $os = OrdemServico::with('veiculo')->findOrFail($origem->ordem_servico_id);
        $condutor = MobilidadeCondutor::withTrashed()->findOrFail($origem->condutor_id);
        $dadosEtapa = $reserva ? (($tipo === 'devolucao' ? $reserva->devolucao : $reserva->entrega) ?? []) : [];
        $aceite = $retirada?->aceite_nome ?? $dadosEtapa['aceite_nome'] ?? '';
        $modelo = MobilidadeModelo::where('tipo', $tipo)->value('texto');
        $cabecalho = implode(PHP_EOL, [
            self::TIPOS[$tipo], 'Oficina: '.$request->user()->empresa->nome_fantasia,
            'OS: '.$os->numeroFormatado().' · Veículo da OS: '.$os->veiculo->placaFormatada(),
            'Condutor: '.$condutor->nome,
        ]);
        $detalhes = $retirada
            ? ['Autorização: '.($retirada->autorizacao ?: '—'), 'Checklist: '.($retirada->checklist ?: '—'), 'Entrega: '.($retirada->entregue_em?->format('d/m/Y H:i') ?: 'pendente')]
            : [
                'Reserva #'.$reserva->id.' · Período: '.$reserva->inicio->format('d/m/Y H:i').' a '.$reserva->fim->format('d/m/Y H:i'),
                'Locadora parceira: '.($reserva->parceira ?: '—').' · Contrato externo: '.($reserva->contrato_externo ?: '—'),
                'Diária: '.\App\Support\Dinheiro::formatar($reserva->diaria_centavos).' · Caução registrada: '.\App\Support\Dinheiro::formatar($reserva->caucao_centavos),
                'Vistoria: '.($dadosEtapa['vistoria'] ?? '—').' · Km: '.($dadosEtapa['km'] ?? '—').' · Combustível (0–4): '.($dadosEtapa['combustivel'] ?? '—'),
            ];
        $documento = MobilidadeDocumento::create([
            'tipo' => $tipo, 'reserva_id' => $reserva?->id, 'retirada_id' => $retirada?->id,
            'user_id' => $request->user()->id, 'aceite_nome' => $aceite,
            'texto' => $cabecalho.PHP_EOL.implode(PHP_EOL, $detalhes).PHP_EOL.PHP_EOL.($modelo ?? ''),
        ]);

        return redirect()->route('mobilidade.documento', $documento);
    }

    public function mostrar(MobilidadeDocumento $documento)
    {
        $this->authorize('mobilidade.ver');

        return response()->view('mobilidade.imprimir', ['documento' => $documento])->header('Cache-Control', 'private, no-store');
    }

    public function anexar(Request $request)
    {
        $this->authorize('mobilidade.editar');
        $dados = $request->validate([
            'reserva_id' => ['nullable', 'integer', 'required_without:retirada_id', 'prohibits:retirada_id'],
            'retirada_id' => ['nullable', 'integer', 'required_without:reserva_id', 'prohibits:reserva_id'],
            'etapa' => ['required', Rule::in(['entrega', 'devolucao', 'retirada'])],
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120', 'dimensions:max_width=5000,max_height=5000'],
        ]);
        $reserva = empty($dados['reserva_id']) ? null : MobilidadeReserva::findOrFail($dados['reserva_id']);
        $retirada = empty($dados['retirada_id']) ? null : MobilidadeRetirada::findOrFail($dados['retirada_id']);
        abort_unless($retirada ? $dados['etapa'] === 'retirada' : match ($dados['etapa']) {
            'entrega' => $reserva->entrega !== null,
            'devolucao' => $reserva->devolucao !== null,
            default => false,
        }, 422);
        $vinculo = ['reserva_id' => $reserva?->id, 'retirada_id' => $retirada?->id, 'etapa' => $dados['etapa']];
        abort_if(MobilidadeFoto::where($vinculo)->count() >= 12, 422, 'Limite de 12 fotos por vistoria.');
        $imagem = @imagecreatefromstring($request->file('foto')->get());
        abort_unless($imagem, 422, 'Imagem inválida.');
        if (max(imagesx($imagem), imagesy($imagem)) > 1600) {
            $largura = min(1600, (int) round(imagesx($imagem) * 1600 / imagesy($imagem)));
            $imagem = imagescale($imagem, max(1, $largura));
            abort_unless($imagem, 422, 'Não foi possível redimensionar a imagem.');
        }
        ob_start();
        imagejpeg($imagem, null, 85);
        $jpeg = ob_get_clean();
        $caminho = 'mobilidade/'.Tenant::id().'/'.Str::uuid().'.jpg';
        abort_unless(Storage::disk('local')->put($caminho, $jpeg), 503, 'Não foi possível guardar a foto.');
        try {
            MobilidadeFoto::create($vinculo + ['caminho' => $caminho]);
        } catch (Throwable $e) {
            Storage::disk('local')->delete($caminho);
            throw $e;
        }

        return back()->with('ok', 'Foto de vistoria anexada.');
    }

    public function foto(MobilidadeFoto $foto)
    {
        $this->authorize('mobilidade.ver');
        abort_unless(Storage::disk('local')->exists($foto->caminho), 404);

        return Storage::disk('local')->response($foto->caminho, null, ['Cache-Control' => 'private, no-store', 'Content-Type' => 'image/jpeg']);
    }
}
