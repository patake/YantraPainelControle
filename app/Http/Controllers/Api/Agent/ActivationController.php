<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Support\Cnpj;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sistemas e licenças livres do cliente, para o assistente de ativação (E2).
 */
class ActivationController extends Controller
{
    public function __invoke(string $cnpj): JsonResponse
    {
        $client = Cnpj::isValid($cnpj)
            ? Client::where('cnpj', Cnpj::normalize($cnpj))->where('active', true)->first()
            : null;

        abort_if($client === null, Response::HTTP_NOT_FOUND, __('Nenhum cliente ativo tem este CNPJ.'));

        $systems = $client->licenses()->with('system')->get()
            ->filter(fn ($license) => $license->valid_until === null || ! $license->valid_until->isPast())
            ->map(fn ($license) => [
                'code' => $license->system->code,
                'name' => $license->system->name,
                'available' => max(0, $license->quantity - $license->usedCount()),
            ])
            ->sortBy('code')
            ->values();

        return response()->json([
            'client' => ['trade_name' => $client->trade_name],
            'systems' => $systems,
        ]);
    }
}
