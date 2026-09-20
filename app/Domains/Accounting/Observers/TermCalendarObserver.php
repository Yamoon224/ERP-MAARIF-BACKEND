<?php

namespace App\Domains\Accounting\Observers;

use App\Domains\Accounting\Services\TuitionService;
use App\Models\Term;

/**
 * Les mois de scolarite d'une annee se deduisent de ses trimestres : quand
 * leurs dates changent, les echeances des inscriptions de l'annee suivent.
 */
final class TermCalendarObserver
{
    public function __construct(private readonly TuitionService $tuition) {}

    public function saved(Term $term): void
    {
        if ($term->wasRecentlyCreated || $term->wasChanged(['starts_at', 'ends_at', 'academic_year'])) {
            $this->tuition->syncAcademicYear($term->academic_year);
        }
    }
}
