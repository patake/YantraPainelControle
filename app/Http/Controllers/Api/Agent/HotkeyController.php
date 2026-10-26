<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Rules\ValidHotkey;
use App\Support\Hotkey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * O assistente do agente informa a combinação de teclas que o Windows aceitou (E7).
 * O painel grava a combinação na parametrização e aumenta a versão dos parâmetros.
 */
class HotkeyController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        // Só o sistema de captura de tela (YO SRC) usa a combinação de teclas (R-EXA-11).
        if (! $device->capturesScreen()) {
            return response()->json(['message' => __('O sistema deste equipamento não usa a captura de tela.')], Response::HTTP_CONFLICT);
        }

        $validated = $request->validate(['hotkey' => ['required', 'string', 'max:40', new ValidHotkey]]);
        $settings = $device->settings()->firstOrCreate()->refresh();
        $settings->update(['hotkey' => Hotkey::normalize($validated['hotkey'])]);

        return response()->json([
            'hotkey' => $settings->hotkey,
            'config_version' => $device->fresh()->config_version,
        ]);
    }
}
