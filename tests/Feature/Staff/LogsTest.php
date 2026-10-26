<?php

namespace Tests\Feature\Staff;

use App\Models\ApiRequestLog;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_screen_filters_by_action(): void
    {
        $this->actingAs(User::factory()->support()->withTwoFactor()->create());
        AuditLog::create(['action' => 'created', 'auditable_type' => 'Client', 'auditable_id' => 'registro-criado', 'created_at' => now()]);
        AuditLog::create(['action' => 'deleted', 'auditable_type' => 'Device', 'auditable_id' => 'registro-apagado', 'created_at' => now()]);

        Livewire::test('pages::staff.logs.audit')
            ->assertSee('registro-criado')
            ->assertSee('registro-apagado')
            ->set('action', 'deleted')
            ->assertDontSee('registro-criado')
            ->assertSee('registro-apagado');
    }

    public function test_requests_screen_filters_errors(): void
    {
        $this->actingAs(User::factory()->support()->withTwoFactor()->create());
        ApiRequestLog::create(['method' => 'GET', 'route' => '/api/v1/agent/commands', 'status' => 200, 'duration_ms' => 5, 'created_at' => now()]);
        ApiRequestLog::create(['method' => 'GET', 'route' => '/api/v1/agent/license', 'status' => 401, 'duration_ms' => 3, 'created_at' => now()]);

        Livewire::test('pages::staff.logs.requests')
            ->set('result', 'erro')
            ->assertSee('/api/v1/agent/license')
            ->assertDontSee('/api/v1/agent/commands');
    }
}
