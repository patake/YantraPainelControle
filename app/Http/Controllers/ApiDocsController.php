<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Publica o contrato OpenAPI da API do agente.
 */
class ApiDocsController extends Controller
{
    public const SPEC_PATH = 'openapi/agent-v1.yaml';

    /**
     * Tela do Swagger UI com o contrato.
     */
    public function index(): View
    {
        return view('api-docs', ['specUrl' => route('api.openapi')]);
    }

    /**
     * Arquivo YAML do contrato.
     */
    public function spec(): BinaryFileResponse
    {
        return response()->file(resource_path(self::SPEC_PATH), [
            'Content-Type' => 'application/yaml; charset=utf-8',
        ]);
    }
}
