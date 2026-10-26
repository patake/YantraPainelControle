<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\System;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_the_four_systems_and_the_example_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(['YC_PRT', 'YO_HUB', 'YO_PRT', 'YO_SRC'], System::orderBy('code')->pluck('code')->all());
        $this->assertSame(3, User::count());
        $this->assertSame(2, Device::usingLicense()->count());
    }

    public function test_system_seeder_can_run_twice(): void
    {
        $this->seed(SystemSeeder::class);
        $this->seed(SystemSeeder::class);

        $this->assertSame(4, System::count());
    }
}
