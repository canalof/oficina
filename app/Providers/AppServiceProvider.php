<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Placa\ConsultaDePlacaNet;
use App\Services\Placa\PlacaDemonstracao;
use App\Services\Placa\ProvedorPlaca;
use App\Support\Perfil;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
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
        // Limites separados: cada um com sua própria contagem
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(6)->by('login|'.mb_strtolower((string) $r->input('email')).'|'.$r->ip()),
            Limit::perMinute(30)->by('login-ip|'.$r->ip()),
        ]);
        RateLimiter::for('placa', fn (Request $r) => Limit::perMinute(30)->by('placa|'.($r->user()?->id ?? $r->ip())));
        RateLimiter::for('orcamento', fn (Request $r) => Limit::perMinute(60)->by('orcamento|'.$r->ip()));
        RateLimiter::for('orcamento-aprovar', fn (Request $r) => Limit::perMinute(10)->by('orcamento-aprovar|'.$r->route('token').'|'.$r->ip()));

        if ($proxies = config('app.trusted_proxies')) {
            \Illuminate\Http\Middleware\TrustProxies::at($proxies === '*' ? '*' : explode(',', $proxies));
        }
        foreach (Perfil::todasPermissoes() as $permissao) {
            Gate::define($permissao, fn (User $user) => $user->pode($permissao));
        }

        Gate::define('plataforma', fn (User $u) => $u->admin_plataforma && $u->doisFatoresAtivo());

        Paginator::defaultView('partials.paginacao');
        Password::defaults(fn () => Password::min(8));
    }
}
