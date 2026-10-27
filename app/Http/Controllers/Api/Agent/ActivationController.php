<?php

namespace App\Http\Controllers\Api\Agent;

use App\Exceptions\AgentRegistrationException;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Support\ActivationCode;
use App\Support\Cnpj;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sistemas e licenças livres do cliente, para o assistente de ativação (E2).
 *
 * O CNPJ é público: a consulta pede também o código de ativação do cliente, no cabeçalho
 * X-Activation-Code (R-LIC-11). Um CNPJ desconhecido e um código errado têm a mesma resposta.
 */
class ActivationController extends Controller
{
    public function __invoke(Request $request, string $cnpj): JsonResponse
    {
        $client = Cnpj::isValid($cnpj)
            ? Client::where('cnpj', Cnpj::normalize($cnpj))->where('active', true)->first()
            : null;

        if ($client !== null && ! ActivationCode::matches($client->activation_code, $request->header('X-Activation-Code'))) {
            $client = null;
        }

        abort_if($client === null, Response::HTTP_NOT_FOUND, AgentRegistrationException::unknownClient()->getMessage());

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
