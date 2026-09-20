<?php

namespace Database\Factories;

use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Term> */
class TermFactory extends Factory
{
    protected $model = Term::class;

    private const NAMES = ['1er trimestre', '2eme trimestre', '3eme trimestre'];

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        // Index deduit d'un compteur plutot que d'un tirage aleatoire : un
        // trimestre est unique par (nom, annee scolaire), et une relation de
        // factory imbriquee (Grade::factory()->count(n)) resout ses n
        // attributs avant d'en enregistrer aucun, ce qui ferait boucler trois
        // fois sur la meme annee scolaire des la quatrieme creation en lot.
        $index = self::$sequence++;
        $name = self::NAMES[$index % count(self::NAMES)];
        $yearOffset = intdiv($index, count(self::NAMES));
        $academicYear = (2025 + $yearOffset).'-'.(2026 + $yearOffset);

        $startsAt = now()->startOfYear()->addYears($yearOffset)->addMonths(($index % count(self::NAMES)) * 3);

        return [
            'name' => $name,
            'academic_year' => $academicYear,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMonths(3)->subDay(),
            'is_current' => false,
        ];
    }

    public function current(): static
    {
        return $this->state(fn () => ['is_current' => true]);
    }
}
