<?php

namespace App\Domains\Academics\Services;

use App\Domains\Academics\Contracts\TeachingAssignmentRepositoryContract;
use App\Domains\Academics\Exceptions\TeachingAssignmentException;
use App\Models\ClassSubjectTeacher;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Qui enseigne quoi, dans quelle classe. Une matiere a un seul enseignant par
 * classe, mais un enseignant peut avoir plusieurs matieres dans plusieurs
 * classes.
 */
final class TeachingAssignmentService
{
    public function __construct(private readonly TeachingAssignmentRepositoryContract $assignments) {}

    /** @return Collection<int, ClassSubjectTeacher> */
    public function forClass(SchoolClass $schoolClass): Collection
    {
        return $this->assignments->forClass($schoolClass);
    }

    /** @return Collection<int, ClassSubjectTeacher> */
    public function forTeacher(User $teacher): Collection
    {
        return $this->assignments->forTeacher($teacher->id);
    }

    public function assign(SchoolClass $schoolClass, Subject $subject, string $teacherId): ClassSubjectTeacher
    {
        return $this->assignments->assign($schoolClass, $subject, $teacherId);
    }

    public function unassign(SchoolClass $schoolClass, Subject $subject): void
    {
        $this->assignments->unassign($schoolClass, $subject);
    }

    /**
     * Un enseignant ne note que les matieres qu'il enseigne a la classe de
     * l'eleve pour l'annee du trimestre. Les comptes qui administrent la
     * structure (`academics.manage`) ne sont pas limites par les affectations.
     */
    public function assertMayGrade(User $user, string $studentId, string $subjectId, string $termId): void
    {
        if ($user->can('academics.manage')) {
            return;
        }

        $academicYear = Term::query()->whereKey($termId)->value('academic_year');

        if ($academicYear === null || ! $this->assignments->teachesStudent($user->id, $studentId, $subjectId, $academicYear)) {
            throw TeachingAssignmentException::notTeacherOf();
        }
    }
}
