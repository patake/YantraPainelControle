<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Operator,
            'active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    public function support(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Support,
        ]);
    }

    /**
     * Usuário com o 2FA cadastrado e confirmado (R-ACE-5).
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(['7KQ2-M9XD', 'R4TB-82LN'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Operador vinculado a um cliente ativo, que pode entrar no painel (R-ACE-6).
     */
    public function operatorWithClient(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Operator,
        ])->hasAttached(Client::factory());
    }

    /**
     * Gestor do hospital vinculado a um cliente ativo (R-ACE-14).
     */
    public function managerWithClient(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Manager,
        ])->hasAttached(Client::factory());
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
