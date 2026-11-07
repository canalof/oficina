<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Loja;
use App\Models\Plano;
use App\Models\User;
use App\Support\Documento;
use App\Support\Perfil;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Cadastro da oficina pelo site: 14 dias de teste grátis, sem cartão. */
class CadastroController extends Controller
{
    public function form(Request $request)
    {
        return view('cadastro', [
            'planos' => Plano::where('ativo', true)->orderBy('ordem')->get(),
            'escolhido' => $request->query('plano', 'profissional'),
        ]);
    }

    public function store(Request $request)
    {
        $request->merge(['cnpj' => Documento::normalizar($request->input('cnpj')) ?: null, 'email' => strtolower((string) $request->input('email'))]);
        $dados = $request->validate([
            'oficina' => ['required', 'string', 'max:120'],
            'cnpj' => ['nullable', function ($a, $v, $fail) {
                if ($v && ! Documento::cnpjValido($v)) {
                    $fail('CNPJ inválido.');
                }
            }, Rule::unique('empresas', 'cnpj')],
            'nome' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'telefone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'plano' => ['required', Rule::exists('planos', 'slug')->where('ativo', true)],
            'aceite' => ['accepted'],
        ], [
            'email.unique' => 'Este e-mail já tem acesso. Use "Esqueci a senha" na tela de entrar.',
            'cnpj.unique' => 'Esta empresa já está cadastrada.',
            'aceite.accepted' => 'É preciso aceitar os termos de uso e a política de privacidade.',
        ], ['oficina' => 'nome da oficina', 'password' => 'senha']);

        $user = DB::transaction(function () use ($dados) {
            $empresa = Empresa::create([
                'nome_fantasia' => $dados['oficina'], 'cnpj' => $dados['cnpj'], 'telefone' => $dados['telefone'], 'email' => $dados['email'],
                'plano_id' => Plano::where('slug', $dados['plano'])->value('id'),
                'assinatura_status' => 'trial', 'trial_ate' => today()->addDays(Empresa::DIAS_TRIAL),
            ]);

            return Tenant::com($empresa->id, function () use ($empresa, $dados) {
                $loja = Loja::create(['nome' => 'Matriz', 'cnpj' => $empresa->cnpj]);

                return User::create([
                    'empresa_id' => $empresa->id, 'loja_id' => $loja->id, 'name' => $dados['nome'], 'email' => $dados['email'],
                    'telefone' => $dados['telefone'], 'perfil' => Perfil::Dono, 'ativo' => true, 'password' => $dados['password'],
                ]);
            });
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('painel')->with('ok', 'Bem-vindo! Você tem '.Empresa::DIAS_TRIAL.' dias de teste grátis. Comece cadastrando sua equipe em Usuários e os serviços no Catálogo.');
    }
}
