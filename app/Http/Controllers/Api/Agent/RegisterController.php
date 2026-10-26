<?php

namespace App\Http\Controllers\Api\Agent;

use App\Actions\Agent\RegisterDevice;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\RegisterDeviceRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registro do equipamento pelo assistente de ativação (E2).
 */
class RegisterController extends Controller
{
    public function __invoke(RegisterDeviceRequest $request, RegisterDevice $register): JsonResponse
    {
        $result = $register->handle($request->validated(), $request->ip());
        $device = $result['device'];

        return response()->json([
            'device_id' => $device->id,
            'token' => $result['token'],
            'status' => $device->status->value,
            'trial_ends_at' => $device->trial_ends_at?->toIso8601String(),
        ], $result['reinstalled'] ? Response::HTTP_OK : Response::HTTP_CREATED);
    }
}
