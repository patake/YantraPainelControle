<?php

namespace App\Providers;

use App\Support\ResponseSigner;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ResponseSigner::class, fn () => new ResponseSigner(
            (string) config('yantra.signing.secret_key'),
            (string) config('yantra.signing.key_id'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Em desenvolvimento e nos testes, acusa N+1, atributos inexistentes e atribuição em massa ignorada.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Consulta por CNPJ e registro do equipamento: 10 tentativas por hora por IP (R-LIC-8).
        RateLimiter::for('agent-activation', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
    }
}
