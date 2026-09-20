<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SchoolClass> */
class SchoolClassFactory extends Factory
{
    protected $model = SchoolClass::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $levels = ['6eme', '5eme', '4eme', '3eme'];
        $sections = ['A', 'B', 'C', 'D', 'E', 'F'];

        // Combinaison deduite d'un compteur plutot que tiree au hasard : une
        // classe est unique par (nom, annee scolaire), et un tirage aleatoire
        // finirait par produire un doublon des la douzieme creation en lot.
        $index = self::$sequence++;
        $level = $levels[intdiv($index, count($sections)) % count($levels)];
        $section = $sections[$index % count($sections)];

        return [
            'name' => "{$level} {$section}",
            'level' => $level,
            'academic_year' => '2025-2026',
            'main_teacher_id' => null,
        ];
    }
}
