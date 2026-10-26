<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aceita só tokens de equipamento com a habilidade "agent" (R-SEG-1).
 * Também grava o sinal de vida do equipamento (devices.last_seen_at).
 */
class EnsureAgentDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $device = $request->user();

        if (! $device instanceof Device || ! $device->tokenCan(Device::TOKEN_ABILITY)) {
            abort(Response::HTTP_FORBIDDEN, 'O token não pertence a um equipamento.');
        }

        $interval = (int) config('yantra.last_seen_interval');

        if ($device->last_seen_at === null || $device->last_seen_at->lt(now()->subSeconds($interval))) {
            $device->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
