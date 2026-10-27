<?php

namespace App\Http\Controllers\Api\Agent;

use App\Enums\ExamFileKind;
use App\Enums\ExamStatus;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Exam;
use App\Models\ExamFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cópia de preview de um arquivo do exame (E6). O painel guarda a cópia em previews/
 * só enquanto o exame está aberto (R-EXA-6 e R-SEG-6).
 */
class ExamFileController extends Controller
{
    /**
     * Extensão do arquivo no S3 para cada tipo.
     *
     * @var array<string, string>
     */
    private const EXTENSIONS = ['pdf' => 'pdf', 'png' => 'png', 'serial_raw' => 'txt', 'serial_data' => 'json'];

    public function store(Request $request, string $exam): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();
        $examModel = Exam::whereBelongsTo($device)->whereKey($exam)->firstOrFail();

        if (! in_array($examModel->status, [ExamStatus::Starting, ExamStatus::Capturing, ExamStatus::Reviewing], true)) {
            return response()->json(['message' => __('O exame não está aberto para arquivos novos.')], Response::HTTP_CONFLICT);
        }

        $kind = $request->input('kind');
        $mimes = is_string($kind) && isset(self::EXTENSIONS[$kind]) ? self::EXTENSIONS[$kind] : 'pdf,png,txt,json';

        $validated = $request->validate([
            'kind' => ['required', Rule::enum(ExamFileKind::class)],
            'sha256' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            // O conteúdo precisa ser do tipo informado: um "pdf" com outro conteúdo fica de fora (E9).
            'file' => ['required', 'file', 'max:51200', 'mimes:'.$mimes],
        ]);

        $upload = $request->file('file');

        if (! hash_equals($validated['sha256'], hash_file('sha256', $upload->getRealPath()))) {
            return response()->json([
                'message' => __('O SHA-256 não confere com o arquivo recebido.'),
                'errors' => ['sha256' => [__('O SHA-256 não confere com o arquivo recebido.')]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $id = (string) Str::uuid7();
        $path = $upload->storeAs($examModel->previewFolder(), $id.'.'.self::EXTENSIONS[$validated['kind']], 's3');

        $file = new ExamFile([
            'exam_id' => $examModel->id,
            'kind' => $validated['kind'],
            'original_name' => mb_substr($upload->getClientOriginalName(), 0, 255),
            'preview_path' => $path,
            'sha256' => $validated['sha256'],
            'size_bytes' => $upload->getSize(),
        ]);
        // O id do arquivo é o nome do objeto no S3.
        $file->id = $id;
        $file->save();

        // O primeiro arquivo leva o exame para a conferência.
        if ($examModel->status !== ExamStatus::Reviewing) {
            $examModel->update(['status' => ExamStatus::Reviewing]);
        }

        return response()->json([
            'id' => $file->id,
            'kind' => $file->kind->value,
            'original_name' => $file->original_name,
            'sha256' => $file->sha256,
            'size_bytes' => $file->size_bytes,
        ], Response::HTTP_CREATED);
    }
}
