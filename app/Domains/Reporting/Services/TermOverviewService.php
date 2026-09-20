<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Academics\Contracts\SchoolClassRepositoryContract;
use App\Domains\Academics\Contracts\SubjectRepositoryContract;
use App\Domains\Attendance\Contracts\AttendanceRepositoryContract;
use App\Domains\Discipline\Contracts\SanctionRepositoryContract;
use App\Domains\Discipline\Contracts\SummonRepositoryContract;
use App\Domains\Grades\Contracts\GradeRepositoryContract;
use App\Domains\Students\Contracts\EnrollmentRepositoryContract;
use App\Models\Enrollment;
use App\Models\Term;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Detail d'un trimestre : classes, matieres, eleves et leurs resultats,
 * notes, sanctions, convocations et presences.
 *
 * Un trimestre appartient a une annee scolaire : ses classes et ses eleves
 * sont ceux de cette annee (une inscription couvre l'annee entiere, donc les
 * trois trimestres). Les notes se rattachent directement au trimestre ; les
 * presences, sanctions et convocations, qui n'ont qu'une date, s'y rattachent
 * par la periode du trimestre (voir Period).
 */
final class TermOverviewService
{
    public function __construct(
        private readonly SchoolClassRepositoryContract $classes,
        private readonly SubjectRepositoryContract $subjects,
        private readonly EnrollmentRepositoryContract $enrollments,
        private readonly GradeRepositoryContract $grades,
        private readonly AttendanceRepositoryContract $attendance,
        private readonly SanctionRepositoryContract $sanctions,
        private readonly SummonRepositoryContract $summons,
    ) {}

    /**
     * Compteurs et indicateurs du trimestre. Sanctions et convocations ne sont
     * renvoyees que si l'appelant a le droit de les consulter.
     *
     * @return array<string, mixed>
     */
    public function summary(Term $term, bool $includeDiscipline): array
    {
        $filter = ['term_id' => $term->id];
        $averages = $this->grades->studentAverages($filter);
        $attendance = $this->attendance->summary($filter, 0);

        return [
            'classes' => $this->classes->count(['academic_year' => $term->academic_year]),
            'subjects' => $this->subjects->forTerm($term)->count(),
            'students' => $this->enrollments->countForYear($term->academic_year),
            'grades' => $this->grades->count($filter),
            'average' => $averages->isEmpty() ? null : round((float) $averages->avg(), 2),
            'attendance' => [
                'present' => $attendance['present'],
                'absent' => $attendance['absent'],
                'late' => $attendance['late'],
                'unjustified_absences' => $attendance['unjustified_absences'],
            ],
            'sanctions' => $includeDiscipline ? $this->sanctions->count($filter) : null,
            'summons' => $includeDiscipline ? $this->summons->count($filter) : null,
        ];
    }

    /**
     * Matieres du trimestre, avec nombre de classes, de notes et moyenne.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function subjects(Term $term): Collection
    {
        return $this->subjects->forTerm($term);
    }

    /**
     * Eleves inscrits pour l'annee du trimestre, avec leur moyenne generale
     * et leurs absences sur le trimestre.
     *
     * @param  array<string, mixed>  $filters  search, school_class_id
     * @return array{page: LengthAwarePaginator<int, Enrollment>, averages: Collection<string, float>, absences: array<string, int>}
     */
    public function students(Term $term, array $filters, int $perPage = 15): array
    {
        $filter = ['term_id' => $term->id];

        return [
            'page' => $this->enrollments->paginateForYear($term->academic_year, $filters, $perPage),
            'averages' => $this->grades->studentAverages($filter),
            'absences' => $this->attendance->absenceCounts($filter),
        ];
    }
}
