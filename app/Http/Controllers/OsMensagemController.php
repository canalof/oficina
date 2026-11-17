<?php

namespace App\Http\Controllers;

use App\Models\OrdemServico;
use App\Support\Perfil;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OsMensagemController extends Controller
{
    public function store(Request $request, OrdemServico $os)
    {
        $this->authorize('os.ver');
        $dados = $request->validate([
            'mensagem' => ['required', 'string', 'max:2000'],
            'perfil_mencionado' => ['nullable', Rule::enum(Perfil::class)],
        ]);
        $os->mensagens()->create($dados + ['user_id' => $request->user()->id]);

        return redirect()->route('os.show', $os)->withFragment('conversa-interna');
    }
}
