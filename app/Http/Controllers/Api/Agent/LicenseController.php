<?php

namespace App\Http\Controllers\Api\Agent;

use App\Enums\DeviceStatus;
use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Estado da licença do equipamento (E3). A resposta é assinada (R-SEG-2). Um equipamento
 * bloqueado continua com o token válido e recebe esta resposta (R-SEG-1).
 */
class LicenseController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user()->load('system');

        return response()->json([
            'device_id' => $device->id,
            'status' => $device->status->value,
            'system' => [
                'code' => $device->system->code,
                'name' => $device->system->name,
                'feature' => $device->system->feature->value,
                'branding' => $device->system->branding ?? (object) [],
            ],
            'trial_ends_at' => $device->trial_ends_at?->toIso8601String(),
            'checked_at' => now()->toIso8601String(),
            'message' => $this->message($device->status),
        ]);
    }

    /**
     * Mensagem da tela de licença bloqueada do agente.
     */
    private function message(DeviceStatus $status): ?string
    {
        return match ($status) {
            DeviceStatus::Expired => __('O período de teste terminou. Fale com a Weega Technologies para ativar a licença.'),
            DeviceStatus::Blocked => __('A licença deste equipamento está bloqueada. Fale com a Weega Technologies.'),
            default => null,
        };
    }
}
