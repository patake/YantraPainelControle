<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Support\DeviceConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Parâmetros assinados do equipamento (E5). Com If-None-Match igual à versão atual,
 * a resposta é 304 sem corpo.
 */
class ConfigController extends Controller
{
    public function __invoke(Request $request): JsonResponse|Response
    {
        /** @var Device $device */
        $device = $request->user();
        $etag = '"'.$device->config_version.'"';

        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response()->noContent(Response::HTTP_NOT_MODIFIED)->setEtag((string) $device->config_version);
        }

        return response()->json(DeviceConfig::for($device))->setEtag((string) $device->config_version);
    }
}
