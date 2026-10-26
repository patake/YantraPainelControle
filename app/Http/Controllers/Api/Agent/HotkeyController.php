<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Rules\ValidHotkey;
use App\Support\Hotkey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O assistente do agente informa a combinação de teclas que o Windows aceitou (E7).
 * O painel grava a combinação na parametrização e aumenta a versão dos parâmetros.
 */
class HotkeyController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate(['hotkey' => ['required', 'string', 'max:40', new ValidHotkey]]);

        /** @var Device $device */
        $device = $request->user();
        $settings = $device->settings()->firstOrCreate()->refresh();
        $settings->update(['hotkey' => Hotkey::normalize($validated['hotkey'])]);

        return response()->json([
            'hotkey' => $settings->hotkey,
            'config_version' => $device->fresh()->config_version,
        ]);
    }
}
