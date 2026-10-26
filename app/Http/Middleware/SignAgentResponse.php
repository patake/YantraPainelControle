<?php

namespace App\Http\Middleware;

use App\Support\ResponseSigner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assina o corpo das respostas da API do agente (R-SEG-2).
 */
class SignAgentResponse
{
    public function __construct(private readonly ResponseSigner $signer) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $body = (string) $response->getContent();
        $timestamp = now()->getTimestamp();

        $response->headers->set(ResponseSigner::HEADER_TIMESTAMP, (string) $timestamp);
        $response->headers->set(ResponseSigner::HEADER_KEY_ID, $this->signer->keyId);
        $response->headers->set(ResponseSigner::HEADER_SIGNATURE, $this->signer->sign($timestamp, $body));

        return $response;
    }
}
