<?php

namespace Tests\Concerns;

use App\Models\SchoolClass;
use App\Models\Term;

/**
 * Annee scolaire type pour les tests : trois trimestres de trois mois
 * (octobre a juin, donc neuf mois de scolarite) et une classe a tarif fixe.
 */
trait BuildsSchoolYear
{
    /**
     * @return array{terms: list<Term>, class: SchoolClass}
     */
    protected function schoolYear(string $academicYear = '2025-2026', float $monthlyFee = 50000, string $className = '6eme A'): array
    {
        [$start] = array_map('intval', explode('-', $academicYear));

        $windows = [
            ['1er trimestre', "{$start}-10-01", "{$start}-12-31"],
            ['2eme trimestre', ($start + 1).'-01-01', ($start + 1).'-03-31'],
            ['3eme trimestre', ($start + 1).'-04-01', ($start + 1).'-06-30'],
        ];

        $terms = array_map(fn (array $window) => Term::factory()->create([
            'name' => $window[0],
            'academic_year' => $academicYear,
            'starts_at' => $window[1],
            'ends_at' => $window[2],
        ]), $windows);

        $class = SchoolClass::factory()->create([
            'name' => $className,
            'level' => '6eme',
            'academic_year' => $academicYear,
            'monthly_fee' => $monthlyFee,
        ]);

        return ['terms' => $terms, 'class' => $class];
    }
}
