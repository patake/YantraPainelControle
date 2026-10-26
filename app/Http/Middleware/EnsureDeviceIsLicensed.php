<?php

namespace App\Http\Middleware;

use App\Enums\DeviceStatus;
use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Um equipamento bloqueado ou com o trial expirado recebe 403 nas rotas de trabalho.
 * O agente consulta GET /license para mostrar a tela de bloqueio (R-SEG-1).
 */
class EnsureDeviceIsLicensed
{
    public function handle(Request $request, Closure $next): Response
    {
        $device = $request->user();

        if ($device instanceof Device && ! in_array($device->status, DeviceStatus::usingLicense(), true)) {
            abort(Response::HTTP_FORBIDDEN, __('A licença deste equipamento não está ativa. Consulte GET /license.'));
        }

        return $next($request);
    }
}
