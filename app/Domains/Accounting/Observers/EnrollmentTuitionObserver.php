<?php

namespace App\Domains\Accounting\Observers;

use App\Domains\Accounting\Services\TuitionService;
use App\Models\Enrollment;

/**
 * Genere l'echeancier de scolarite des qu'une inscription est creee ou
 * change de classe (donc de tarif) : la dette de l'eleve existe des
 * l'inscription, sans attendre que quelqu'un ouvre son releve.
 */
final class EnrollmentTuitionObserver
{
    public function __construct(private readonly TuitionService $tuition) {}

    public function saved(Enrollment $enrollment): void
    {
        if ($enrollment->wasRecentlyCreated || $enrollment->wasChanged('school_class_id')) {
            $this->tuition->ensureInstallments($enrollment);
        }
    }
}
