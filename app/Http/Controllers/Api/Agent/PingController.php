<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Teste de conexão do agente: confere o token e a assinatura da resposta.
 */
class PingController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        return response()->json([
            'device_id' => $device->getKey(),
            'status' => $device->status->value,
            'config_version' => $device->config_version,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
