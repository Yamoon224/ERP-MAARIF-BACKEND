<?php

namespace Database\Factories;

use App\Domains\Discipline\Enums\SanctionType;
use App\Models\Sanction;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Sanction> */
class SanctionFactory extends Factory
{
    protected $model = Sanction::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'type' => SanctionType::Warning,
            'reason' => fake()->sentence(),
            'start_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'end_date' => null,
            'created_by' => User::factory(),
            'notified_at' => null,
        ];
    }
}
