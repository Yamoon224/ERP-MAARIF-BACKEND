<?php

namespace App\Domains\Students\Services;

use App\Domains\Academics\Contracts\SchoolClassRepositoryContract;
use App\Domains\Students\Contracts\EnrollmentRepositoryContract;
use App\Domains\Students\Contracts\StudentRepositoryContract;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Inscriptions annuelles : un eleve s'inscrit pour une annee scolaire entiere
 * (ses trois trimestres), dans une classe de cette annee.
 */
final class EnrollmentService
{
    public function __construct(
        private readonly EnrollmentRepositoryContract $enrollments,
        private readonly SchoolClassRepositoryContract $classes,
        private readonly StudentRepositoryContract $students,
    ) {}

    /** @return Collection<int, Enrollment> */
    public function history(Student $student): Collection
    {
        return $this->enrollments->forStudent($student->id);
    }

    /**
     * Inscrit l'eleve dans une classe pour l'annee de cette classe (nouvelle
     * annee, ou transfert de classe au sein de la meme annee) et en fait sa
     * classe actuelle.
     */
    public function enroll(Student $student, string $schoolClassId): Enrollment
    {
        $class = $this->classes->findOrFail($schoolClassId);

        $enrollment = $this->enrollments->upsertForYear(
            $student->id,
            $class->academic_year,
            $class->id,
            now()->toDateString(),
        );

        $this->students->update($student, ['school_class_id' => $class->id]);

        return $this->enrollments->findOrFail($enrollment->id);
    }
}
