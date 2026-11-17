<?php

namespace App\Http\Controllers;

use App\Models\ConferenciaCnh;
use App\Models\MobilidadeCondutor;
use App\Models\OrdemServico;
use App\Services\Cnh\CatalogoCnh;
use App\Services\Cnh\ConsultaCnh;
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
        ]);
        $os = empty($dados['os']) ? null : OrdemServico::findOrFail($dados['os']);

        if ($dados['provedor'] === 'senatran') {
            abort_unless(Documento::cpfValido((string) ($condutor->cpf ?? ''))
                && preg_match('/^\d{11}$/D', (string) ($condutor->registro_cnh ?? '')) === 1,
                422, 'Cadastre o CPF e o registro da CNH do condutor antes de consultar a SENATRAN.');
            $entrada = ['cpf' => (string) $condutor->cpf, 'registro' => (string) $condutor->registro_cnh];
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

        $mensagem = $avaliacao['liberacao'] === 'producao'
            ? 'Consulta oficial concluída: CNH conferida em produção.'
            : 'Consulta registrada em homologação ('.$avaliacao['resultado'].'). Este resultado não libera retirada nem locação.';

        return redirect()->route('mobilidade.condutor.show', ['condutor' => $condutor, 'os' => $os?->id])
            ->with($avaliacao['resultado'] === 'divergente' ? 'erro' : 'ok', $mensagem);
    }
}
