<?php

namespace App\Http\Controllers\Api\Agent;

use App\Enums\ExamStatus;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Exam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * O agente envia os dados que extraiu da porta serial com as regras regex do exame (E8).
 * Um envio novo junta os campos aos campos já recebidos; o valor novo vale para o mesmo campo.
 */
class ExamDataController extends Controller
{
    public function store(Request $request, string $exam): JsonResponse|HttpResponse
    {
        /** @var Device $device */
        $device = $request->user();
        $examModel = Exam::whereBelongsTo($device)->whereKey($exam)->firstOrFail();

        if (! in_array($examModel->status, [ExamStatus::Starting, ExamStatus::Capturing, ExamStatus::Reviewing], true)) {
            return response()->json(['message' => __('O exame não está aberto para dados novos.')], Response::HTTP_CONFLICT);
        }

        $validated = $request->validate([
            'fields' => ['required', 'array', 'min:1', 'max:50'],
            'fields.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $invalid = collect(array_keys($validated['fields']))->reject(fn ($key) => preg_match('/^[A-Z0-9_]{1,60}$/', (string) $key));

        if ($invalid->isNotEmpty()) {
            return response()->json([
                'message' => __('O nome do campo usa letras maiúsculas, números e "_", com até 60 caracteres.'),
                'errors' => ['fields' => [__('Campos inválidos: :fields.', ['fields' => $invalid->join(', ')])]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $examModel->update([
            'serial_data' => array_merge($examModel->serial_data ?? [], $validated['fields']),
            'serial_received_at' => now(),
            // Os dados, como o primeiro arquivo, levam o exame para a conferência.
            'status' => ExamStatus::Reviewing,
        ]);

        return response()->noContent();
    }
}
