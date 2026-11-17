<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Rota de módulo opcional: só abre com o módulo ligado em Empresa › Módulos. */
class ExigirModulo
{
    public function handle(Request $request, Closure $next, string $modulo)
    {
        $empresa = $request->user()?->empresa;
        abort_unless($empresa && $empresa->temModulo($modulo), 403, 'Este recurso está desligado. O dono pode ligar em Ajustes › Empresa › Módulos.');

        return $next($request);
    }
}
