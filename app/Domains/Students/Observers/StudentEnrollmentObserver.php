<?php

namespace App\Domains\Students\Observers;

use App\Domains\Academics\Contracts\SchoolClassRepositoryContract;
use App\Domains\Students\Contracts\EnrollmentRepositoryContract;
use App\Models\Student;

/**
 * Garde l'historique d'inscriptions en phase avec la classe de l'eleve.
 *
 * `students.school_class_id` designe la classe *actuelle*. Chaque fois qu'un
 * eleve est cree ou change de classe, l'inscription de l'annee de cette
 * classe est creee ou mise a jour : quel que soit le chemin d'ecriture
 * (formulaire d'inscription, import, seeder), l'eleve est toujours inscrit pour
 * l'annee scolaire de sa classe.
 */
final class StudentEnrollmentObserver
{
    public function __construct(
        private readonly EnrollmentRepositoryContract $enrollments,
        private readonly SchoolClassRepositoryContract $classes,
    ) {}

    public function saved(Student $student): void
    {
        if ($student->school_class_id === null) {
            return;
        }

        if (! $student->wasRecentlyCreated && ! $student->wasChanged('school_class_id')) {
            return;
        }

        $class = $this->classes->findOrFail($student->school_class_id);

        $this->enrollments->upsertForYear(
            $student->id,
            $class->academic_year,
            $class->id,
            now()->toDateString(),
        );
    }
}
