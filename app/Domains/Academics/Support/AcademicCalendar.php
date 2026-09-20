<?php

namespace App\Domains\Academics\Support;

use App\Models\Term;
use Carbon\CarbonImmutable;

/**
 * Mois scolaires d'une annee, deduits de ses trimestres (une annee en compte
 * trois) : du mois du premier jour du premier trimestre au mois du dernier
 * jour du dernier trimestre. Deduire ces mois des trimestres plutot que d'un
 * calendrier code en dur suit l'etablissement quand il decale sa rentree.
 */
final class AcademicCalendar
{
    private function __construct() {}

    /**
     * Premier jour de chaque mois scolaire, dans l'ordre. Liste vide si
     * l'annee n'a encore aucun trimestre.
     *
     * @return list<CarbonImmutable>
     */
    public static function months(string $academicYear): array
    {
        $bounds = Term::query()
            ->where('academic_year', $academicYear)
            ->selectRaw('min(starts_at) as first_day, max(ends_at) as last_day')
            ->first();

        if ($bounds?->first_day === null || $bounds->last_day === null) {
            return [];
        }

        $month = CarbonImmutable::parse($bounds->first_day)->startOfMonth();
        $last = CarbonImmutable::parse($bounds->last_day)->startOfMonth();

        $months = [];
        while ($month <= $last) {
            $months[] = $month;
            $month = $month->addMonth();
        }

        return $months;
    }
}
