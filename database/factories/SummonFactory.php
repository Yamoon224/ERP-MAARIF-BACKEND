<?php

namespace Database\Factories;

use App\Domains\Discipline\Enums\SummonStatus;
use App\Models\Student;
use App\Models\Summon;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Summon> */
class SummonFactory extends Factory
{
    protected $model = Summon::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'reason' => fake()->randomElement(['Retards repetes', 'Comportement en classe', 'Resultats en baisse']),
            'scheduled_at' => fake()->dateTimeBetween('now', '+2 weeks'),
            'location' => 'Bureau de la direction',
            'status' => SummonStatus::Pending,
            'created_by' => User::factory(),
            'notified_at' => null,
        ];
    }
}
