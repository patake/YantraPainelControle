<?php

namespace Tests\Feature\Models;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Models\AgentCommand;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AgentCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_the_payload_encrypted(): void
    {
        $command = AgentCommand::create([
            'device_id' => Device::factory()->create()->id,
            'type' => CommandType::RecordReport,
            'payload' => ['tasy_username' => 'medico', 'tasy_password' => 'senha-secreta'],
            'expires_at' => now()->addSeconds(CommandType::RecordReport->ttlSeconds()),
        ]);

        $raw = DB::table('agent_commands')->where('id', $command->id)->value('payload');

        $this->assertStringNotContainsString('senha-secreta', (string) $raw);
        $this->assertSame('senha-secreta', $command->fresh()->payload['tasy_password']);
        $this->assertArrayNotHasKey('payload', $command->fresh()->toArray());
    }

    public function test_deliverable_scope_keeps_only_pending_commands_that_did_not_expire(): void
    {
        $device = Device::factory()->create();
        $base = ['device_id' => $device->id, 'type' => CommandType::Sync];

        $valid = AgentCommand::create($base + ['expires_at' => now()->addMinute()]);
        AgentCommand::create($base + ['expires_at' => now()->subSecond()]);
        AgentCommand::create($base + ['expires_at' => now()->addMinute(), 'status' => CommandStatus::Delivered]);

        $this->assertSame([$valid->id], AgentCommand::deliverable()->pluck('id')->all());
    }
}
