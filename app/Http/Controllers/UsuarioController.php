<?php

namespace App\Http\Controllers;

use App\Models\Loja;
use App\Models\User;
use App\Support\Perfil;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UsuarioController extends Controller
{
    public function index()
    {
        return view('usuarios.index', ['usuarios' => User::daEmpresaAtual()->with('loja')->orderByDesc('ativo')->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('usuarios.form', ['usuario' => new User(['perfil' => Perfil::Consultor, 'ativo' => true]), 'lojas' => Loja::orderBy('nome')->get()]);
    }

    public function store(Request $request)
    {
        $plano = $request->user()->empresa->plano;
        if ($plano?->limite_usuarios !== null && User::daEmpresaAtual()->where('ativo', true)->count() >= $plano->limite_usuarios) {
            return back()->withInput()->with('erro', "O plano {$plano->nome} permite até {$plano->limite_usuarios} usuários ativos. Troque de plano em Assinatura ou desative alguém.");
        }
        $dados = $this->validar($request);
        $dados['empresa_id'] = Tenant::id();
        User::create($dados);

        return redirect()->route('usuarios.index')->with('ok', 'Usuário criado. Passe o e-mail e a senha para a pessoa.');
    }

    public function edit(int $usuario)
    {
        return view('usuarios.form', ['usuario' => $this->achar($usuario), 'lojas' => Loja::orderBy('nome')->get()]);
    }

    public function update(Request $request, int $usuario)
    {
        $u = $this->achar($usuario);
        $dados = $this->validar($request, $u);

        if ($u->id === $request->user()->id && ($dados['perfil'] !== Perfil::Dono->value || ! $dados['ativo'])) {
            return back()->with('erro', 'Você não pode tirar o seu próprio acesso de dono.');
        }
        if (empty($dados['password'])) {
            unset($dados['password']);
        }
        $u->update($dados);

        return redirect()->route('usuarios.index')->with('ok', 'Usuário atualizado.');
    }

    private function achar(int $id): User
    {
        return User::daEmpresaAtual()->findOrFail($id);
    }

    private function validar(Request $request, ?User $u = null): array
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($u?->id)],
            'telefone' => ['nullable', 'string', 'max:20'],
            'perfil' => ['required', Rule::enum(Perfil::class)],
            'comissao_percentual' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'loja_id' => ['nullable', Rule::exists('lojas', 'id')->where('empresa_id', Tenant::id())],
            'password' => [$u ? 'nullable' : 'required', Password::defaults()],
        ], ['email.unique' => 'Este e-mail já é usado por outro usuário.'], ['name' => 'nome', 'password' => 'senha']);
        $dados['ativo'] = $request->boolean('ativo');
        $dados['comissao_percentual'] = (float) ($dados['comissao_percentual'] ?? 0);

        return $dados;
    }
}
