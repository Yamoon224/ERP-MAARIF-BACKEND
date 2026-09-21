<?php

namespace App\Domains\Academics\Contracts;

use App\Models\ClassSubjectTeacher;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Support\Collection;

interface TeachingAssignmentRepositoryContract
{
    /**
     * Matieres d'une classe qui ont un enseignant, par ordre alphabetique.
     *
     * @return Collection<int, ClassSubjectTeacher>
     */
    public function forClass(SchoolClass $schoolClass): Collection;

    /**
     * Tout ce qu'un enseignant enseigne : une ligne par couple classe + matiere.
     *
     * @return Collection<int, ClassSubjectTeacher>
     */
    public function forTeacher(string $teacherId): Collection;

    /** Donne la matiere de la classe a cet enseignant ; s'il y en avait un autre, il est remplace. */
    public function assign(SchoolClass $schoolClass, Subject $subject, string $teacherId): ClassSubjectTeacher;

    public function unassign(SchoolClass $schoolClass, Subject $subject): void;

    /** Cet enseignant a-t-il la matiere dans la classe ou l'eleve etait inscrit cette annee-la ? */
    public function teachesStudent(string $teacherId, string $studentId, string $subjectId, string $academicYear): bool;
}
