<?php

namespace Tests\Feature;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Cabeçalhos de segurança da revisão da E9.
 */
class SecurityHeadersTest extends TestCase
{
    public function test_every_response_has_the_security_headers(): void
    {
        foreach (['/login', '/api/v1/agent/public-key'] as $uri) {
            $response = $this->get($uri);

            $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
            $response->assertHeaderMissing('Strict-Transport-Security');
        }
    }

    public function test_https_responses_turn_on_hsts(): void
    {
        $this->get('https://localhost/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_behind_the_load_balancer_the_panel_sees_the_client_ip_and_https(): void
    {
        TrustProxies::at('*');

        try {
            Route::get('/_teste-proxy', fn (Request $request) => [$request->ip(), $request->isSecure()]);

            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
                ->withHeaders(['X-Forwarded-For' => '200.10.20.30', 'X-Forwarded-Proto' => 'https'])
                ->get('/_teste-proxy')
                ->assertExactJson(['200.10.20.30', true]);
        } finally {
            TrustProxies::flushState();
        }
    }
}
