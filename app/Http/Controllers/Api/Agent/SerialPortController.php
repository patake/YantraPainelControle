<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * O assistente da porta COM do agente informa a porta que achou e o resultado do teste (E8).
 * O painel grava a porta na parametrização. Sem isso, o próximo GET /config trocaria a porta
 * escolhida no computador pela porta antiga do painel.
 */
class SerialPortController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        // Só o sistema de porta serial (YO HUB) usa a porta COM (R-EXA-13).
        if (! $device->readsSerialPort()) {
            return response()->json(['message' => __('O sistema deste equipamento não usa a porta serial.')], Response::HTTP_CONFLICT);
        }

        $validated = $request->validate([
            'port' => ['required', 'string', 'regex:/^COM\d{1,3}$/i'],
            'adapter' => ['nullable', 'string', 'max:255'],
            'test_passed' => ['required', 'boolean'],
        ], [
            'port.regex' => __('Use o formato COM3.'),
        ]);

        $settings = $device->settings()->firstOrCreate()->refresh();
        $settings->update([
            'serial_port' => strtoupper($validated['port']),
            'serial_adapter' => $validated['adapter'] ?? null,
            'serial_test_passed' => $validated['test_passed'],
            'serial_reported_at' => now(),
        ]);

        return response()->json([
            'port' => $settings->serial_port,
            'adapter' => $settings->serial_adapter,
            'test_passed' => $settings->serial_test_passed,
            'config_version' => $device->fresh()->config_version,
        ]);
    }
}
