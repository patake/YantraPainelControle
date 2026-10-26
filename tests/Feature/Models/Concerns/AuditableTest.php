<?php

namespace Tests\Feature\Models\Concerns;

use App\Enums\DbType;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ErpConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R-SEG-4 e R-SEG-5: auditoria com valor antigo e valor novo, sem segredos.
 */
class AuditableTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_the_acting_user_and_the_deleted_values(): void
    {
        $admin = User::factory()->admin()->create();
        $client = Client::factory()->create(['trade_name' => 'Hospital Alfa']);
        $this->actingAs($admin);

        $client->delete();

        $log = AuditLog::where('action', 'deleted')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('Hospital Alfa', $log->old_values['trade_name']);
    }

    public function test_masks_encrypted_values_on_create_and_update(): void
    {
        $connection = ErpConnection::create([
            'client_id' => Client::factory()->create()->id,
            'db_type' => DbType::Oracle,
            'host' => 'oracle.hosp.local',
            'port' => 1521,
            'service_name' => 'TASYPRD',
            'username' => 'YANTRA_APP',
            'password' => 'senha-do-banco',
        ]);
        $connection->update(['password' => 'senha-nova']);

        $logs = AuditLog::where('auditable_type', 'ErpConnection')->get();

        $this->assertSame('[alterado]', $logs->firstWhere('action', 'created')->new_values['password']);
        $this->assertSame(['password' => '[alterado]'], $logs->firstWhere('action', 'updated')->new_values);
        $this->assertStringNotContainsString('senha', $logs->toJson());
    }

    public function test_never_records_the_user_password_or_two_factor_secret(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $user->update(['password' => 'outra-senha-123']);

        $json = AuditLog::where('auditable_type', 'User')->get()->toJson();

        $this->assertStringNotContainsString('outra-senha', $json);
        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', $json);
        $this->assertStringNotContainsString('two_factor_recovery_codes', $json);
    }

    public function test_ignores_an_update_that_only_touches_ignored_fields(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['last_login_at' => now()])->save();

        $this->assertSame(0, AuditLog::where('action', 'updated')->count());
    }
}
