<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Recusa do registro do equipamento, com o código HTTP do contrato da API.
 */
class AgentRegistrationException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }

    public static function unknownClient(): self
    {
        // Um CNPJ desconhecido e um código de ativação errado têm a mesma mensagem (R-LIC-11).
        return new self(__('Nenhum cliente ativo tem este CNPJ com este código de ativação.'), Response::HTTP_NOT_FOUND);
    }

    public static function systemNotLicensed(string $system): self
    {
        return new self(__('O cliente não tem licença do sistema :system.', ['system' => $system]), Response::HTTP_UNPROCESSABLE_ENTITY, 'system');
    }

    public static function noAvailableSeat(string $system): self
    {
        return new self(__('Todas as licenças do sistema :system estão em uso. Fale com a Weega.', ['system' => $system]), Response::HTTP_CONFLICT);
    }

    public static function signatureOfAnotherClient(): self
    {
        return new self(__('Este equipamento já está registrado em outro cliente. Fale com a Weega.'), Response::HTTP_CONFLICT);
    }

    public function render(): JsonResponse
    {
        $body = ['message' => $this->getMessage()];

        if ($this->field !== null) {
            $body['errors'] = [$this->field => [$this->getMessage()]];
        }

        return response()->json($body, $this->status);
    }
}
