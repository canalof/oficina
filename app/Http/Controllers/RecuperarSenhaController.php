<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as RegraSenha;

class RecuperarSenhaController extends Controller
{
    public function form()
    {
        return view('auth.esqueci');
    }

    /** Sempre responde igual, exista ou não o e-mail (não revela quem tem conta). */
    public function enviar(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink(['email' => strtolower($request->input('email')), 'ativo' => true]);

        return back()->with('ok', 'Se o e-mail estiver cadastrado, enviamos um link para criar uma senha nova. Ele vale por 60 minutos.');
    }

    public function redefinirForm(Request $request, string $token)
    {
        return view('auth.redefinir', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function redefinir(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', RegraSenha::defaults()],
        ], [], ['password' => 'nova senha']);

        $status = Password::reset(
            ['email' => strtolower($request->input('email'))] + $request->only('password', 'password_confirmation', 'token'),
            function (User $user, string $senha) {
                $user->forceFill(['password' => Hash::make($senha), 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('ok', 'Senha alterada. Entre com a senha nova.')
            : back()->withErrors(['email' => 'Link inválido ou vencido. Peça um novo.']);
    }
}
