<?php

namespace App\Domains\Results\Services;

use App\Domains\Academics\Contracts\TermRepositoryContract;
use App\Domains\Academics\Services\AcademicYearService;
use App\Domains\Grades\Contracts\GradeRepositoryContract;
use App\Domains\Results\Contracts\PromotionDecisionRepositoryContract;
use App\Domains\Results\Enums\PromotionDecisionType;
use App\Domains\Results\Exceptions\ResultsException;
use App\Domains\Results\Support\AverageCalculator;
use App\Domains\Results\Support\Mention;
use App\Domains\Results\Support\ResultPeriod;
use App\Domains\Students\Contracts\EnrollmentRepositoryContract;
use App\Models\Enrollment;
use App\Models\PromotionDecision;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Support\Collection;

/**
 * Resultats des eleves par trimestre, par semestre et sur l'annee, avec le
 * rang dans la classe et la decision de passage en fin d'annee.
 *
 * Le classement se fait dans la classe de l'eleve pour l'annee concernee (voir
 * Enrollment), jamais sur l'ensemble de l'etablissement. La moyenne de passage
 * (config `school.pass_mark`) ne fait que *suggerer* une decision ; celle qui
 * fait foi est la decision enregistree par l'administration.
 */
final class ResultsService
{
    public function __construct(
        private readonly TermRepositoryContract $terms,
        private readonly EnrollmentRepositoryContract $enrollments,
        private readonly GradeRepositoryContract $grades,
        private readonly PromotionDecisionRepositoryContract $decisions,
        private readonly AcademicYearService $years,
    ) {}

    public function passMark(): float
    {
        return (float) config('school.pass_mark', 10);
    }

    /**
     * Periodes calculables pour une annee : chaque trimestre, les semestres
     * que le calendrier permet, puis l'annee.
     *
     * @return list<ResultPeriod>
     */
    public function periodsFor(string $academicYear): array
    {
        $terms = $this->termsOf($academicYear);
        $periods = $terms->map(fn (Term $term) => $this->termPeriod($term))->all();

        if ($terms->count() >= 2) {
            $periods[] = $this->semesterPeriod($academicYear, 1, $terms);
        }
        if ($terms->count() >= 3) {
            $periods[] = $this->semesterPeriod($academicYear, 2, $terms);
        }
        if ($terms->isNotEmpty()) {
            $periods[] = $this->annualPeriod($academicYear, $terms);
        }

        return $periods;
    }

    public function period(string $academicYear, string $kind, ?string $termId = null, ?int $semester = null): ResultPeriod
    {
        $terms = $this->termsOf($academicYear);

        return match ($kind) {
            ResultPeriod::TERM => $this->termPeriod(
                $terms->firstWhere('id', $termId) ?? throw ResultsException::termOutsideYear($academicYear),
            ),
            ResultPeriod::SEMESTER => $this->semesterPeriod($academicYear, $semester ?? 1, $terms),
            default => $this->annualPeriod($academicYear, $terms),
        };
    }

    /**
     * Classement d'une classe sur une periode.
     *
     * @return array<string, mixed>
     */
    public function forClass(SchoolClass $class, ResultPeriod $period): array
    {
        $enrollments = $this->cohort($class);
        $grades = $this->grades->forStudentsAndTerms($enrollments->pluck('student_id')->all(), $period->termIds);
        $computed = $this->compute($enrollments, $grades, $period);

        $saved = $period->isAnnual()
            ? $this->decisions->forEnrollments($enrollments->pluck('id')->all())
            : new Collection;

        $rows = $computed['rows']->map(fn (array $row) => [
            'enrollment_id' => $row['enrollment']->id,
            'student' => $this->studentPayload($row['enrollment']->student),
            'average' => $row['average'],
            'rank' => $row['rank'],
            'mention' => Mention::forAverage($row['average']),
            'grades_count' => $row['grades_count'],
            'subjects_count' => count($row['subjects']),
            ...$this->decisionPayload($period->isAnnual(), $row['average'], $saved->get($row['enrollment']->id)),
        ])->values()->all();

        return [
            'school_class' => [
                'id' => $class->id,
                'name' => $class->name,
                'level' => $class->level,
                'academic_year' => $class->academic_year,
            ],
            'period' => $period->toArray(),
            'pass_mark' => $this->passMark(),
            'stats' => $computed['stats'],
            'rows' => $rows,
        ];
    }

    /**
     * Resultats d'un eleve sur toutes les periodes d'une annee.
     *
     * @return array<string, mixed>
     */
    public function forStudent(Student $student, ?string $academicYear = null): array
    {
        $year = $academicYear ?? $this->years->defaultYear() ?? throw ResultsException::noAcademicYear();

        $enrollment = $this->enrollments->forStudent($student->id)->firstWhere('academic_year', $year);
        $class = $enrollment?->schoolClass;

        // Les camarades de classe servent au rang ; sans inscription, l'eleve
        // est seul et n'a pas de rang.
        $cohort = $enrollment !== null && $class !== null ? $this->cohort($class) : new Collection;
        $studentIds = $cohort->isEmpty() ? [$student->id] : $cohort->pluck('student_id')->all();

        $periods = $this->periodsFor($year);
        $allTermIds = collect($periods)->flatMap(fn (ResultPeriod $period) => $period->termIds)->unique()->values()->all();
        $grades = $this->grades->forStudentsAndTerms($studentIds, $allTermIds);

        $results = array_map(function (ResultPeriod $period) use ($student, $cohort, $grades): array {
            if ($cohort->isNotEmpty()) {
                $computed = $this->compute($cohort, $grades, $period);
                $own = $computed['rows']->first(fn (array $candidate) => $candidate['enrollment']->student_id === $student->id);
                $average = $own['average'];
                $rank = $own['rank'];
                $subjects = $own['subjects'];
                $ranked = $computed['stats']['ranked'];
            } else {
                $own = AverageCalculator::compute($grades->where('student_id', $student->id)->whereIn('term_id', $period->termIds));
                $average = $own['overall_average'];
                $rank = null;
                $subjects = $own['subjects'];
                $ranked = null;
            }

            return [
                ...$period->toArray(),
                'average' => $average,
                'rank' => $rank,
                'ranked_count' => $ranked,
                'mention' => Mention::forAverage($average),
                'subjects' => $subjects,
            ];
        }, $periods);

        $annual = collect($results)->firstWhere('kind', ResultPeriod::ANNUAL);
        $saved = $enrollment !== null ? $this->decisions->forEnrollments([$enrollment->id])->get($enrollment->id) : null;

        return [
            'student' => ['id' => $student->id, 'name' => $student->fullName(), 'matricule' => $student->matricule],
            'academic_year' => $year,
            'school_class' => $class !== null ? ['id' => $class->id, 'name' => $class->name, 'level' => $class->level] : null,
            'pass_mark' => $this->passMark(),
            'periods' => $results,
            ...$this->decisionPayload($annual !== null, $annual['average'] ?? null, $saved),
        ];
    }

    /** Enregistre (ou remplace) la decision de passage d'une inscription. */
    public function saveDecision(Enrollment $enrollment, PromotionDecisionType $decision, ?string $note, string $userId): PromotionDecision
    {
        $average = $this->annualAverage($enrollment);

        return $this->decisions->save($enrollment->id, $decision->value, $average, $note, $userId);
    }

    /**
     * Valide d'un coup les decisions suggerees d'une classe : celles qui n'ont
     * pas encore de decision enregistree et dont l'eleve a une moyenne
     * annuelle. Les decisions deja prises (et donc eventuellement corrigees a
     * la main) ne sont jamais ecrasees.
     *
     * @return int nombre de decisions enregistrees
     */
    public function validateClassDecisions(SchoolClass $class, string $userId): int
    {
        $result = $this->forClass($class, $this->period($class->academic_year, ResultPeriod::ANNUAL));

        $count = 0;
        foreach ($result['rows'] as $row) {
            if ($row['decision'] !== null || $row['suggested_decision'] === null) {
                continue;
            }

            $this->decisions->save($row['enrollment_id'], $row['suggested_decision']['value'], $row['average'], null, $userId);
            $count++;
        }

        return $count;
    }

    private function annualAverage(Enrollment $enrollment): ?float
    {
        $terms = $this->termsOf($enrollment->academic_year);
        $termIds = $terms->pluck('id')->all();

        return AverageCalculator::compute($this->grades->forStudentsAndTerms([$enrollment->student_id], $termIds))['overall_average'];
    }

    /**
     * Inscrits de la classe, eleve charge.
     *
     * @return Collection<int, Enrollment>
     */
    private function cohort(SchoolClass $class): Collection
    {
        return $this->enrollments->forClass($class->id)
            ->load('student:id,first_name,last_name,matricule,is_active')
            ->sortBy(fn (Enrollment $enrollment) => mb_strtolower($enrollment->student->last_name.' '.$enrollment->student->first_name))
            ->values();
    }

    /**
     * @param  Collection<int, Enrollment>  $enrollments
     * @param  Collection<int, \App\Models\Grade>  $grades
     * @return array{rows: Collection<int, array<string, mixed>>, stats: array<string, mixed>}
     */
    private function compute(Collection $enrollments, Collection $grades, ResultPeriod $period): array
    {
        $byStudent = $grades->groupBy('student_id');

        $rows = $enrollments->map(function (Enrollment $enrollment) use ($byStudent, $period): array {
            $studentGrades = ($byStudent->get($enrollment->student_id) ?? new Collection)->whereIn('term_id', $period->termIds);
            $result = AverageCalculator::compute($studentGrades);

            return [
                'enrollment' => $enrollment,
                'average' => $result['overall_average'],
                'grades_count' => $result['grades_count'],
                'subjects' => $result['subjects'],
            ];
        });

        $ranks = AverageCalculator::rank($rows->mapWithKeys(fn (array $row) => [$row['enrollment']->id => $row['average']])->all());

        $rows = $rows
            ->map(fn (array $row) => [...$row, 'rank' => $ranks[$row['enrollment']->id]])
            // Les premiers d'abord ; les eleves sans moyenne a la fin, dans l'ordre alphabetique deja acquis.
            ->sortBy(fn (array $row) => $row['rank'] ?? PHP_INT_MAX)
            ->values();

        $averages = $rows->pluck('average')->filter(fn ($average) => $average !== null);
        $passed = $averages->filter(fn ($average) => $average >= $this->passMark())->count();

        return [
            'rows' => $rows,
            'stats' => [
                'students' => $rows->count(),
                'ranked' => $averages->count(),
                'average' => $averages->isEmpty() ? null : round((float) $averages->avg(), 2),
                'highest' => $averages->isEmpty() ? null : (float) $averages->max(),
                'lowest' => $averages->isEmpty() ? null : (float) $averages->min(),
                'passed' => $passed,
                'pass_rate' => $averages->isEmpty() ? null : round($passed / $averages->count() * 100, 1),
            ],
        ];
    }

    /**
     * Decision suggeree et decision enregistree. Vides hors de la periode
     * annuelle : on ne decide d'un passage qu'a la fin de l'annee.
     *
     * @return array{suggested_decision: array{value: string, label: string}|null, decision: array<string, mixed>|null}
     */
    private function decisionPayload(bool $annual, ?float $average, ?PromotionDecision $saved): array
    {
        if (! $annual) {
            return ['suggested_decision' => null, 'decision' => null];
        }

        $suggested = $average === null
            ? null
            : ($average >= $this->passMark() ? PromotionDecisionType::Admitted : PromotionDecisionType::Repeat);

        return [
            'suggested_decision' => $suggested === null ? null : ['value' => $suggested->value, 'label' => $suggested->label()],
            'decision' => $saved === null ? null : [
                'value' => $saved->decision->value,
                'label' => $saved->decision->label(),
                'note' => $saved->note,
                'average' => $saved->average === null ? null : (float) $saved->average,
                'decided_at' => $saved->decided_at->toIso8601String(),
            ],
        ];
    }

    /** @return array{id: string, name: string, matricule: string, is_active: bool} */
    private function studentPayload(Student $student): array
    {
        return [
            'id' => $student->id,
            'name' => $student->fullName(),
            'matricule' => $student->matricule,
            'is_active' => $student->is_active,
        ];
    }

    /** @return Collection<int, Term> trimestres de l'annee, dans l'ordre chronologique */
    private function termsOf(string $academicYear): Collection
    {
        return $this->terms->all()
            ->where('academic_year', $academicYear)
            ->sortBy(fn (Term $term) => $term->starts_at)
            ->values();
    }

    private function termPeriod(Term $term): ResultPeriod
    {
        return new ResultPeriod(ResultPeriod::TERM, $term->id, $term->name, $term->academic_year, [$term->id]);
    }

    /** @param  Collection<int, Term>  $terms */
    private function semesterPeriod(string $academicYear, int $semester, Collection $terms): ResultPeriod
    {
        // Semestre 1 : trimestres 1 et 2. Semestre 2 : trimestres 2 et 3.
        $slice = $terms->slice($semester === 2 ? 1 : 0, 2)->values();

        if ($slice->count() < 2) {
            throw ResultsException::incompleteCalendar($academicYear, $semester === 2 ? '2eme semestre' : '1er semestre');
        }

        return new ResultPeriod(
            ResultPeriod::SEMESTER,
            "semester-{$semester}",
            $semester === 2 ? '2eme semestre' : '1er semestre',
            $academicYear,
            $slice->pluck('id')->all(),
        );
    }

    /** @param  Collection<int, Term>  $terms */
    private function annualPeriod(string $academicYear, Collection $terms): ResultPeriod
    {
        if ($terms->isEmpty()) {
            throw ResultsException::incompleteCalendar($academicYear, 'annuel');
        }

        return new ResultPeriod(ResultPeriod::ANNUAL, 'annual', "Annuel {$academicYear}", $academicYear, $terms->pluck('id')->all());
    }
}
