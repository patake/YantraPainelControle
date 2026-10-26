<?php

namespace App\Providers;

use App\Support\ResponseSigner;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

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

        // Senha com no mínimo 12 caracteres (R-ACE-8).
        Password::defaults(fn () => Password::min(12));

        // A recuperação de senha responde igual para e-mail cadastrado ou não (R-ACE-9).
        $this->app->singleton(FailedPasswordResetLinkRequestResponse::class, fn () => new class implements FailedPasswordResetLinkRequestResponse
        {
            public function toResponse($request)
            {
                return back()->with('status', __('Se o e-mail existe no cadastro, o link chega em poucos minutos.'));
            }
        });
        $this->app->singleton(SuccessfulPasswordResetLinkRequestResponse::class, fn () => new class implements SuccessfulPasswordResetLinkRequestResponse
        {
            public function toResponse($request)
            {
                return back()->with('status', __('Se o e-mail existe no cadastro, o link chega em poucos minutos.'));
            }
        });

        // Consulta por CNPJ e registro do equipamento: 10 tentativas por hora por IP (R-LIC-8).
        RateLimiter::for('agent-activation', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
    }
}
