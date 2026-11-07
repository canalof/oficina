<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function form()
    {
        return view('auth.entrar');
    }

    public function entrar(Request $request)
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [], ['password' => 'senha']);

        $credenciais = ['email' => strtolower($dados['email']), 'password' => $dados['password'], 'ativo' => true];
        if (! Auth::validate($credenciais)) {
            return back()->withErrors(['email' => 'E-mail ou senha incorretos.'])->onlyInput('email');
        }
        $user = \App\Models\User::where('email', $credenciais['email'])->first();

        // Verificação em duas etapas: guarda quem é e pede o código do aplicativo
        if ($user->doisFatoresAtivo()) {
            $request->session()->put('dois_fatores', ['id' => $user->id, 'lembrar' => $request->boolean('lembrar'), 'ate' => now()->addMinutes(5)->timestamp]);

            return redirect()->route('login.codigo');
        }

        Auth::login($user, $request->boolean('lembrar'));
        $request->session()->regenerate();
        $request->user()->forceFill(['ultimo_acesso_em' => now()])->saveQuietly();

        return redirect()->intended(route('painel'));
    }

    public function sair(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function senhaForm()
    {
        return view('auth.senha');
    }

    public function senhaSalvar(Request $request)
    {
        $request->validate([
            'senha_atual' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [], ['password' => 'nova senha', 'senha_atual' => 'senha atual']);

        $request->user()->update(['password' => Hash::make($request->input('password'))]);

        return redirect()->route('painel')->with('ok', 'Senha alterada.');
    }
}
