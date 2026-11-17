<?php

namespace App\Http\Controllers;

use App\Models\ConferenciaCnh;
use App\Models\MobilidadeCondutor;
use App\Models\OrdemServico;
use App\Services\Cnh\CatalogoCnh;
use App\Services\Cnh\ConsultaCnh;
use App\Services\Cnh\VioApiPrivada;
use App\Support\Documento;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CnhConsultaController extends Controller
{
    public function store(Request $request, MobilidadeCondutor $condutor, ConsultaCnh $consultaCnh)
    {
        $this->authorize('mobilidade.editar');
        $dados = $request->validate([
            'provedor' => ['required', Rule::in(array_keys(CatalogoCnh::IMPLEMENTADOS))],
            'finalidade' => ['required', Rule::in(array_keys(ConferenciaCnh::FINALIDADES))],
            'os' => ['nullable', 'integer'],
            'confirmacao' => ['accepted'],
            'qr' => ['required_if:provedor,vio_serpro', 'nullable', 'file', 'max:16'],
            'documento' => ['required_if:provedor,vio_privado', 'nullable', 'file', 'mimetypes:'.implode(',', VioApiPrivada::TIPOS), 'max:10240'],
        ], [
            'documento.required_if' => 'Envie a foto ou o PDF da CNH.',
            'documento.mimetypes' => 'Envie a CNH em JPG, PNG, WEBP ou PDF.',
            'documento.max' => 'Envie um arquivo de até 10 MB.',
        ]);
        $os = empty($dados['os']) ? null : OrdemServico::findOrFail($dados['os']);

        if ($dados['provedor'] === 'senatran') {
            abort_unless(Documento::cpfValido((string) ($condutor->cpf ?? ''))
                && preg_match('/^\d{11}$/D', (string) ($condutor->registro_cnh ?? '')) === 1,
                422, 'Cadastre o CPF e o registro da CNH do condutor antes de consultar a SENATRAN.');
            $entrada = ['cpf' => (string) $condutor->cpf, 'registro' => (string) $condutor->registro_cnh];
        } elseif ($dados['provedor'] === 'vio_privado') {
            $arquivo = $request->file('documento');
            abort_unless($arquivo && $arquivo->isValid(), 422, 'Envie a foto ou o PDF da CNH.');
            // O arquivo vai direto ao fornecedor; o Torque não guarda a imagem.
            $entrada = ['arquivo' => (string) $arquivo->get(), 'tipo' => (string) $arquivo->getMimeType()];
        } else {
            $arquivo = $request->file('qr');
            abort_unless($arquivo && $arquivo->isValid(), 422, 'Informe o arquivo binário do QR.');
            $conteudo = (string) $arquivo->get();
            abort_unless($conteudo !== '', 422, 'Informe o QR binário de teste.');
            $entrada = ['qr' => $conteudo];
        }

        try {
            $avaliacao = $consultaCnh->executar($request->user(), $dados['provedor'], $entrada, [
                'finalidade' => $dados['finalidade'],
                'condutor' => $condutor,
                'ordem_servico' => $os,
            ]);
        } catch (DomainException $erro) {
            return back()->with('erro', $erro->getMessage());
        }

        $preenchidos = $dados['provedor'] === 'vio_privado' ? $this->preencherCondutor($condutor, (array) ($avaliacao['lidos'] ?? [])) : [];

        $mensagem = match (true) {
            $avaliacao['liberacao'] === 'producao' => 'Consulta oficial concluída: CNH conferida em produção.',
            $avaliacao['autenticidade'] === 'documental' && $avaliacao['ambiente'] === 'producao' => 'QR Code VIO lido pela VIO API. Validade '
                .(($avaliacao['dimensoes']['validade']['estado'] ?? '') === 'vencida' ? 'VENCIDA' : ($avaliacao['dimensoes']['validade']['valor'] ? date('d/m/Y', strtotime($avaliacao['dimensoes']['validade']['valor'])) : 'não informada'))
                .'. Confira com o condutor e registre a conferência assistida abaixo.',
            default => 'Consulta registrada em homologação ('.$avaliacao['resultado'].'). Este resultado não libera retirada nem locação.',
        };
        if ($preenchidos['preenchidos'] ?? []) {
            $mensagem .= ' Cadastro do condutor preenchido com: '.implode(', ', $preenchidos['preenchidos']).'.';
        }
        foreach ($preenchidos['divergentes'] ?? [] as $aviso) {
            $mensagem .= ' Atenção: '.$aviso;
        }

        return redirect()->route('mobilidade.condutor.show', ['condutor' => $condutor, 'os' => $os?->id])
            ->with($avaliacao['resultado'] === 'divergente' ? 'erro' : 'ok', $mensagem);
    }

    /**
     * Preenche no cadastro do condutor só o que está vazio. O que já estava
     * preenchido e diverge da leitura vira aviso: a equipe decide.
     *
     * @return array{preenchidos: list<string>, divergentes: list<string>}
     */
    private function preencherCondutor(MobilidadeCondutor $condutor, array $lidos): array
    {
        $preenchidos = [];
        $divergentes = [];
        $validade = isset($lidos['validade']) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', (string) $lidos['validade']) ? $lidos['validade'] : null;
        $categoria = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) ($lidos['categoria'] ?? '')) ?? '');
        $categoria = in_array($categoria, ['ACC', 'A', 'B', 'C', 'D', 'E', 'AB', 'AC', 'AD', 'AE'], true) ? $categoria : null;

        if ($validade) {
            if (! $condutor->validade) {
                $condutor->validade = $validade;
                $preenchidos[] = 'validade';
            } elseif ($condutor->validade->format('Y-m-d') !== $validade) {
                $divergentes[] = 'a validade do cadastro ('.$condutor->validade->format('d/m/Y').') é diferente da lida no QR Code ('.date('d/m/Y', strtotime($validade)).').';
            }
        }
        if ($categoria) {
            if (! filled($condutor->categoria)) {
                $condutor->categoria = $categoria;
                $preenchidos[] = 'categoria';
            } elseif ($condutor->categoria !== $categoria) {
                $divergentes[] = 'a categoria do cadastro ('.$condutor->categoria.') é diferente da lida no QR Code ('.$categoria.').';
            }
        }
        if (isset($lidos['registro']) && ! filled($condutor->registro_cnh)) {
            $condutor->registro_cnh = $lidos['registro'];
            $preenchidos[] = 'registro da CNH';
        }
        if ($condutor->isDirty()) {
            $condutor->save();
        }

        return ['preenchidos' => $preenchidos, 'divergentes' => $divergentes];
    }
}
