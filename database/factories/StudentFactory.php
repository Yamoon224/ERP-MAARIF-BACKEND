<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<Student> */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    /** Mot de passe hache une seule fois pour toute la suite de tests. */
    private static ?string $password = null;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $gender = fake()->randomElement(['M', 'F']);

        return [
            // Sequence en memoire plutot que App\Domains\Students\Support\MatriculeGenerator :
            // ce dernier lit le dernier matricule enregistre en base, or
            // Factory::count(n)->create() evalue les n definitions avant
            // d'en enregistrer aucune — les n eleves y liraient donc tous
            // "aucun matricule existant" et se verraient attribuer le meme.
            'matricule' => sprintf('MAA-%s-%06d', now()->year, ++self::$sequence),
            'password' => self::$password ??= Hash::make('password'),
            'first_name' => $gender === 'M' ? fake()->firstNameMale() : fake()->firstNameFemale(),
            'last_name' => fake()->lastName(),
            'gender' => $gender,
            'birth_date' => fake()->dateTimeBetween('-16 years', '-10 years'),
            'school_class_id' => null,
            'guardian_name' => fake()->name(),
            'guardian_phone' => '+224'.fake()->numerify('6########'),
            'guardian_email' => fake()->safeEmail(),
            'address' => fake()->address(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
