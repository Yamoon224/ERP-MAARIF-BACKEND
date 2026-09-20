<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Subject> */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    private const NAMES = [
        'Mathematiques', 'Francais', 'Anglais', 'Sciences physiques',
        'Sciences de la vie', 'Histoire-Geographie', 'Education civique',
        'Education physique', 'Arts plastiques', 'Informatique',
    ];

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->randomElement(self::NAMES);

        // Le code embarque un suffixe unique plutot que d'etre derive du seul
        // nom : une relation de factory imbriquee (Grade::factory()->count(n))
        // peut declencher plusieurs matieres du meme nom dans le meme lot, et
        // `code` est unique en base alors que `name` ne l'est pas.
        return [
            'name' => $name,
            'code' => strtoupper(substr(str_replace([' ', '-'], '', $name), 0, 4)).'-'.strtoupper(Str::random(4)),
            'coefficient' => fake()->randomElement([1, 1.5, 2, 3, 4]),
        ];
    }
}
