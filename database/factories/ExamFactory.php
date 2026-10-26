<?php

namespace Database\Factories;

use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Models\Device;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => Device::factory(),
            'client_id' => fn (array $attributes) => Device::find($attributes['device_id'])->client_id,
            'exam_type_id' => fn (array $attributes) => ExamType::factory()->state(['client_id' => $attributes['client_id']]),
            'operator_id' => User::factory()->state(['role' => UserRole::Operator]),
            'status' => ExamStatus::Capturing,
            'temp_folder' => 'C:\ProgramData\YantraAgente\capturas\ECG\\'.now()->format('Ymd-His'),
            'started_at' => now(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExamStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
