<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** Verificação em duas etapas (código do aplicativo autenticador). */
class DoisFatoresController extends Controller
{
    public function form(Request $request)
    {
        $p = $request->session()->get('dois_fatores');
        if (! $p || $p['ate'] < now()->timestamp) {
            return redirect()->route('login');
        }

        return view('auth.codigo');
    }

    public function verificar(Request $request)
    {
        $p = $request->session()->get('dois_fatores');
        if (! $p || $p['ate'] < now()->timestamp) {
            return redirect()->route('login')->withErrors(['email' => 'O tempo para digitar o código acabou. Entre de novo.']);
        }
        $request->validate(['codigo' => ['required', 'string', 'max:20']], [], ['codigo' => 'código']);
        $user = User::find($p['id']);
        $codigo = trim((string) $request->input('codigo'));

        $ok = $user && $user->ativo && Totp::valido($user->dois_fatores_segredo, $codigo);
        if (! $ok && $user) {
            // Código de recuperação (uso único)
            $codigos = $user->dois_fatores_recuperacao ?? [];
            $normal = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $codigo));
            foreach ($codigos as $i => $c) {
                if (hash_equals($c, $normal)) {
                    unset($codigos[$i]);
                    $user->forceFill(['dois_fatores_recuperacao' => array_values($codigos)])->save();
                    $ok = true;
                    break;
                }
            }
        }
        if (! $ok) {
            return back()->withErrors(['codigo' => 'Código inválido. Confira o aplicativo e tente de novo.']);
        }

        $request->session()->forget('dois_fatores');
        Auth::login($user, $p['lembrar']);
        $request->session()->regenerate();
        $user->forceFill(['ultimo_acesso_em' => now()])->saveQuietly();

        return redirect()->intended(route('painel'));
    }

    public function seguranca(Request $request)
    {
        $user = $request->user();
        $segredo = null;
        if (! $user->doisFatoresAtivo()) {
            $segredo = $request->session()->get('novo_segredo') ?: Totp::novoSegredo();
            $request->session()->put('novo_segredo', $segredo);
        }

        return view('conta.seguranca', [
            'user' => $user,
            'segredo' => $segredo,
            'url' => $segredo ? Totp::url($segredo, $user->email) : null,
            'codigos' => session('codigos_novos'),
        ]);
    }

    public function ativar(Request $request)
    {
        $segredo = $request->session()->get('novo_segredo');
        $request->validate(['codigo' => ['required']], [], ['codigo' => 'código']);
        if (! $segredo || ! Totp::valido($segredo, (string) $request->input('codigo'))) {
            return back()->withErrors(['codigo' => 'Código não confere. Confira se o relógio do celular está certo e digite o código atual.']);
        }
        $codigos = collect(range(1, 8))->map(fn () => strtoupper(Str::random(10)))->all();
        $request->user()->forceFill([
            'dois_fatores_segredo' => $segredo, 'dois_fatores_ativo_em' => now(), 'dois_fatores_recuperacao' => $codigos,
        ])->save();
        $request->session()->forget('novo_segredo');

        return redirect()->route('seguranca')->with('ok', 'Verificação em duas etapas ativada.')->with('codigos_novos', $codigos);
    }

    public function desativar(Request $request)
    {
        $request->validate(['senha' => ['required', 'current_password']], [], ['senha' => 'senha']);
        if ($request->user()->exigeDoisFatores()) {
            return back()->with('erro', 'Para administradores da plataforma, a verificação em duas etapas é obrigatória.');
        }
        $request->user()->forceFill(['dois_fatores_segredo' => null, 'dois_fatores_ativo_em' => null, 'dois_fatores_recuperacao' => null])->save();

        return back()->with('ok', 'Verificação em duas etapas desativada.');
    }
}
