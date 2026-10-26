<?php

namespace Tests\Feature\Policies;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R-ACE-2 a R-ACE-4: quem vê e quem altera clientes.
 */
class ClientPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_matrix(): void
    {
        $client = Client::factory()->create();
        $admin = User::factory()->admin()->create();
        $support = User::factory()->support()->create();
        $linked = User::factory()->create();
        $linked->clients()->attach($client);
        $stranger = User::factory()->create();

        $this->assertTrue($admin->can('viewAny', Client::class));
        $this->assertTrue($support->can('viewAny', Client::class));
        $this->assertFalse($linked->can('viewAny', Client::class));

        $this->assertTrue($support->can('view', $client));
        $this->assertTrue($linked->can('view', $client));
        $this->assertFalse($stranger->can('view', $client));

        $this->assertTrue($admin->can('create', Client::class));
        $this->assertFalse($support->can('create', Client::class));
        $this->assertTrue($admin->can('update', $client));
        $this->assertFalse($support->can('update', $client));
        $this->assertFalse($linked->can('update', $client));
    }

    public function test_visible_to_scope_limits_an_operator_to_the_linked_clients(): void
    {
        $linked = Client::factory()->create();
        Client::factory()->create();
        $operator = User::factory()->create();
        $operator->clients()->attach($linked);

        $this->assertSame([$linked->id], Client::visibleTo($operator)->pluck('id')->all());
        $this->assertSame(2, Client::visibleTo(User::factory()->support()->create())->count());
    }
}
