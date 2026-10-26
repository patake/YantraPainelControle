<?php

namespace App\Http\Controllers\Api\Agent;

use App\Actions\Agent\ApplyCommandResult;
use App\Enums\CommandStatus;
use App\Http\Controllers\Controller;
use App\Models\AgentCommand;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Canal de comandos do painel para o agente, por consulta periódica (E6).
 */
class CommandController extends Controller
{
    /**
     * Intervalo da consulta com exame aberto e sem exame, em segundos.
     */
    private const POLL_WITH_EXAM = 5;

    private const POLL_IDLE = 30;

    /**
     * Comandos pendentes e válidos. O painel entrega cada comando uma vez.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        $commands = DB::transaction(function () use ($device) {
            $commands = AgentCommand::query()
                ->whereBelongsTo($device)
                ->deliverable()
                ->oldest()
                ->lockForUpdate()
                ->get();

            AgentCommand::whereKey($commands->modelKeys())->update([
                'status' => CommandStatus::Delivered->value,
                'delivered_at' => now(),
            ]);

            return $commands;
        });

        $openExamId = $device->exams()->open()->value('id');

        return response()->json([
            'data' => $commands->map(fn (AgentCommand $command) => [
                'id' => $command->id,
                'type' => $command->type->value,
                'exam_id' => $command->exam_id,
                'expires_at' => $command->expires_at->toIso8601String(),
                'payload' => $command->payload ?? (object) [],
            ])->values(),
            'next_poll_seconds' => $openExamId !== null ? self::POLL_WITH_EXAM : self::POLL_IDLE,
            // O agente encerra o ciclo quando o exame termina no painel sem um comando,
            // por exemplo ao concluir sem um laudo com erro (R-EXA-9).
            'open_exam_id' => $openExamId,
        ]);
    }

    /**
     * Resultado de um comando entregue.
     */
    public function result(Request $request, string $command, ApplyCommandResult $apply): Response|JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        $agentCommand = AgentCommand::whereBelongsTo($device)->whereKey($command)->firstOrFail();

        if ($agentCommand->status === CommandStatus::Expired || $agentCommand->expires_at->isPast()) {
            return response()->json(['message' => __('O comando expirou antes do resultado.')], Response::HTTP_GONE);
        }

        if ($agentCommand->status !== CommandStatus::Delivered) {
            return response()->json(['message' => __('O comando já tem resultado.')], Response::HTTP_CONFLICT);
        }

        $validated = $request->validate([
            'success' => ['required', 'boolean'],
            'error' => ['nullable', 'string', 'max:2000'],
            'data' => ['nullable', 'array'],
        ]);

        $apply->handle($agentCommand, $validated);

        return response()->noContent();
    }
}
