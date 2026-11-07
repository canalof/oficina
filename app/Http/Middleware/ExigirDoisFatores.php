<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Quem é obrigado a usar 2 etapas (admin da plataforma) precisa ativar antes de continuar. */
class ExigirDoisFatores
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if ($u && $u->exigeDoisFatores() && ! $u->doisFatoresAtivo() && ! $request->routeIs('seguranca*', 'logout')) {
            return redirect()->route('seguranca')->with('erro', 'Ative a verificação em duas etapas para continuar.');
        }

        return $next($request);
    }
}
