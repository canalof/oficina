<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Placa\ConsultaDePlacaNet;
use App\Services\Placa\PlacaDemonstracao;
use App\Services\Placa\ProvedorPlaca;
use App\Support\Perfil;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProvedorPlaca::class, function () {
            $cfg = config('placa');
            if ($cfg['provedor'] === 'consultadeplaca' && ! empty($cfg['consultadeplaca']['chave'])) {
                return new ConsultaDePlacaNet(
                    $cfg['consultadeplaca']['chave'],
                    $cfg['consultadeplaca']['url'],
                    $cfg['consultadeplaca']['custo_centavos'],
                );
            }

            return new PlacaDemonstracao;
        });
    }

    public function boot(): void
    {
        foreach (Perfil::todasPermissoes() as $permissao) {
            Gate::define($permissao, fn (User $user) => $user->pode($permissao));
        }

        Paginator::defaultView('partials.paginacao');
        Password::defaults(fn () => Password::min(8));
    }
}
