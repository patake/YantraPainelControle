<?php

namespace App\Http\Middleware;

use App\Models\ApiRequestLog;
use App\Models\Device;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registra cada requisição da API, sem corpo e sem cabeçalhos (R-SEG-3).
 */
class LogApiRequest
{
    private const STARTED_AT = 'api_log_started_at';

    public function handle(Request $request, Closure $next): Response
    {
        // O Laravel chama terminate() numa instância nova: o início fica na requisição.
        $request->attributes->set(self::STARTED_AT, microtime(true));

        return $next($request);
    }

    /**
     * Grava depois de enviar a resposta, para não atrasar o agente.
     */
    public function terminate(Request $request, Response $response): void
    {
        $actor = $request->user();

        ApiRequestLog::create([
            'device_id' => $actor instanceof Device ? $actor->getKey() : null,
            'user_id' => $actor instanceof User ? $actor->getKey() : null,
            'method' => $request->method(),
            'route' => '/'.ltrim($request->route()?->uri() ?? $request->path(), '/'),
            'status' => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - (float) $request->attributes->get(self::STARTED_AT, microtime(true))) * 1000),
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);
    }
}
