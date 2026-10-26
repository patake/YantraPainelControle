<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentRelease;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Versão disponível do agente (E4). O download usa uma URL assinada do S3.
 */
class LatestUpdateController extends Controller
{
    /**
     * Validade da URL de download, em minutos.
     */
    private const DOWNLOAD_MINUTES = 10;

    public function __invoke(Request $request): JsonResponse|Response
    {
        $validated = $request->validate([
            'current' => ['required', 'string', 'max:20', 'regex:/^\d+\.\d+\.\d+$/'],
        ]);

        /** @var Device $device */
        $device = $request->user();
        $release = AgentRelease::latestFor($device->release_channel);

        if ($release === null || version_compare($release->version, $validated['current'], '<=')) {
            return response()->noContent();
        }

        $expiresAt = now()->addMinutes(self::DOWNLOAD_MINUTES);

        return response()->json([
            'version' => $release->version,
            'channel' => $release->channel->value,
            'sha256' => $release->sha256,
            'size_bytes' => $release->size_bytes,
            'notes' => $release->notes,
            'download_url' => Storage::disk('s3')->temporaryUrl($release->storage_path, $expiresAt),
            'download_expires_at' => $expiresAt->toIso8601String(),
        ]);
    }
}
