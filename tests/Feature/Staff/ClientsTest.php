<?php

namespace Tests\Feature\Staff;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->withTwoFactor()->create();
    }

    public function test_lists_clients_and_filters_by_name_or_cnpj(): void
    {
        Client::factory()->create(['trade_name' => 'Hospital Alfa', 'cnpj' => '11222333000181']);
        Client::factory()->create(['trade_name' => 'Clínica Beta']);

        $this->actingAs(User::factory()->support()->withTwoFactor()->create());

        Livewire::test('pages::staff.clients.index')
            ->assertSee('Hospital Alfa')
            ->assertSee('Clínica Beta')
            ->set('search', '11.222')
            ->assertSee('Hospital Alfa')
            ->assertDontSee('Clínica Beta');
    }

    public function test_support_does_not_see_the_create_button_and_cannot_open_the_form(): void
    {
        $this->actingAs(User::factory()->support()->withTwoFactor()->create());

        $this->get(route('staff.clients.index'))->assertOk()->assertDontSee('Novo cliente');
        $this->get(route('staff.clients.create'))->assertForbidden();
    }

    public function test_admin_creates_a_client_with_the_cnpj_digits_and_an_audit_record(): void
    {
        $this->actingAs($admin = $this->admin());

        Livewire::test('pages::staff.clients.form')
            ->set('cnpj', '11.222.333/0001-81')
            ->set('legal_name', 'Hospital Alfa Ltda.')
            ->set('trade_name', 'Hospital Alfa')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $client = Client::sole();
        $this->assertSame('11222333000181', $client->cnpj);
        $this->assertDatabaseHas(AuditLog::class, [
            'user_id' => $admin->id,
            'action' => 'created',
            'auditable_type' => 'Client',
            'auditable_id' => (string) $client->id,
        ]);
    }

    public function test_rejects_an_invalid_cnpj(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::staff.clients.form')
            ->set('cnpj', '11.222.333/0001-82')
            ->set('legal_name', 'Hospital Alfa Ltda.')
            ->set('trade_name', 'Hospital Alfa')
            ->call('save')
            ->assertHasErrors(['cnpj' => 'O CNPJ informado não é válido.']);

        $this->assertDatabaseCount(Client::class, 0);
    }

    public function test_rejects_a_cnpj_that_already_exists_even_for_a_deleted_client(): void
    {
        Client::factory()->create(['cnpj' => '11222333000181'])->delete();
        $this->actingAs($this->admin());

        Livewire::test('pages::staff.clients.form')
            ->set('cnpj', '11222333000181')
            ->set('legal_name', 'Outro Ltda.')
            ->set('trade_name', 'Outro')
            ->call('save')
            ->assertHasErrors(['cnpj' => 'Já existe um cliente com este CNPJ.']);
    }

    public function test_admin_updates_a_client_and_the_audit_keeps_old_and_new_values(): void
    {
        $client = Client::factory()->create(['trade_name' => 'Nome antigo']);
        $this->actingAs($this->admin());

        Livewire::test('pages::staff.clients.form', ['client' => $client])
            ->set('trade_name', 'Nome novo')
            ->call('save')
            ->assertHasNoErrors();

        $log = AuditLog::where('action', 'updated')->sole();
        $this->assertSame(['trade_name' => 'Nome antigo'], $log->old_values);
        $this->assertSame(['trade_name' => 'Nome novo'], $log->new_values);
    }

    public function test_admin_links_and_unlinks_an_operator(): void
    {
        $client = Client::factory()->create();
        $operator = User::factory()->create();
        $this->actingAs($this->admin());

        $component = Livewire::test('pages::staff.clients.show', ['client' => $client])
            ->set('tab', 'usuarios')
            ->set('operatorToLink', $operator->id)
            ->call('linkOperator')
            ->assertHasNoErrors();

        $this->assertTrue($operator->clients()->whereKey($client->id)->exists());

        $component->call('unlinkOperator', $operator->id);

        $this->assertFalse($operator->clients()->whereKey($client->id)->exists());
        $links = AuditLog::where('auditable_type', 'User')
            ->where('auditable_id', (string) $operator->id)
            ->where('action', 'updated')
            ->oldest('id')
            ->get();

        $this->assertSame([[], [$client->id]], $links->pluck('old_values.client_ids')->all());
        $this->assertSame([[$client->id], []], $links->pluck('new_values.client_ids')->all());
    }

    public function test_support_cannot_link_an_operator(): void
    {
        $client = Client::factory()->create();
        $operator = User::factory()->create();
        $this->actingAs(User::factory()->support()->withTwoFactor()->create());

        Livewire::test('pages::staff.clients.show', ['client' => $client])
            ->set('operatorToLink', $operator->id)
            ->call('linkOperator')
            ->assertForbidden();
    }
}
