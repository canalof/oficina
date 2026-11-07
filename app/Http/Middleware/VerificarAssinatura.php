<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Oficina com teste vencido, suspensa ou cancelada só acessa a tela da assinatura. */
class VerificarAssinatura
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if ($u && ! $u->admin_plataforma && $u->empresa?->bloqueada()
            && ! $request->routeIs('assinatura*', 'logout', 'seguranca*', 'senha*', 'plataforma*')) {
            return redirect()->route('assinatura');
        }

        return $next($request);
    }
}
