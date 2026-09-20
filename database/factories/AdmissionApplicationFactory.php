<?php

namespace Database\Factories;

use App\Domains\Admissions\Enums\AdmissionStatus;
use App\Models\AdmissionApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdmissionApplication> */
class AdmissionApplicationFactory extends Factory
{
    protected $model = AdmissionApplication::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $gender = fake()->randomElement(['M', 'F']);

        return [
            // Sequence en memoire pour la meme raison que StudentFactory : la
            // reference est unique en base.
            'reference' => sprintf('ADM-%s-%06d', now()->year, ++self::$sequence),
            'academic_year' => '2025-2026',
            'level' => '6eme',
            'first_name' => $gender === 'M' ? fake()->firstNameMale() : fake()->firstNameFemale(),
            'last_name' => fake()->lastName(),
            'gender' => $gender,
            'birth_date' => fake()->dateTimeBetween('-16 years', '-10 years'),
            'previous_school' => null,
            'guardian_name' => fake()->name(),
            'guardian_phone' => '+224'.fake()->numerify('6########'),
            'guardian_email' => fake()->safeEmail(),
            'address' => fake()->address(),
            'notes' => null,
            'status' => AdmissionStatus::Pending,
            'submitted_on' => now()->toDateString(),
        ];
    }

    public function status(AdmissionStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
