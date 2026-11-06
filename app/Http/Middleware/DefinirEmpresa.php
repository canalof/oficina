<?php

namespace App\Http\Middleware;

use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define a empresa da requisição pelo usuário logado e bloqueia
 * usuários ou empresas desativados.
 */
class DefinirEmpresa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->ativo || ! $user->empresa?->ativo)) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'Acesso desativado. Fale com o responsável pela sua empresa.']);
        }

        Tenant::usar($user?->empresa_id);

        try {
            return $next($request);
        } finally {
            Tenant::usar(null);
        }
    }
}
