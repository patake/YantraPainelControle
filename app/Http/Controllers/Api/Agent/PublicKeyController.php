<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Support\ResponseSigner;
use Illuminate\Http\JsonResponse;

/**
 * Chave pública Ed25519 que confere as respostas assinadas.
 * O instalador do agente traz a mesma chave; esta rota serve para conferência.
 */
class PublicKeyController extends Controller
{
    public function __invoke(ResponseSigner $signer): JsonResponse
    {
        return response()->json([
            'key_id' => $signer->keyId,
            'algorithm' => 'ed25519',
            'public_key' => $signer->publicKey(),
        ]);
    }
}
