<?php

namespace App\Domains\Grades\Repositories;

use App\Domains\Grades\Contracts\GradeRepositoryContract;
use App\Domains\Shared\Support\Period;
use App\Domains\Shared\Support\Sort;
use App\Models\Grade;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EloquentGradeRepository implements GradeRepositoryContract
{
    /** @var array<string, string> */
    private const SORTABLE = [
        'recorded_at' => 'recorded_at',
        'value' => 'value',
    ];

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->with(['student:id,first_name,last_name,matricule', 'subject:id,name,code', 'term:id,name'])
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'recorded_at', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function count(array $filters = []): int
    {
        return $this->filtered($filters)->count();
    }

    public function studentAverages(array $filters = []): Collection
    {
        $rows = $this->filtered($filters)
            ->join('subjects', 'subjects.id', '=', 'grades.subject_id')
            ->groupBy('grades.student_id', 'grades.subject_id', 'subjects.coefficient')
            ->selectRaw('grades.student_id as student_id, subjects.coefficient as coefficient, avg(grades.value * 20.0 / nullif(grades.max_value, 0)) as subject_average')
            ->get();

        return $rows->groupBy('student_id')->map(function (Collection $subjects): float {
            $weighted = $subjects->sum(fn ($row) => (float) $row->subject_average * (float) $row->coefficient);
            $coefficients = $subjects->sum(fn ($row) => (float) $row->coefficient);

            return $coefficients > 0 ? round($weighted / $coefficients, 2) : 0.0;
        });
    }

    /**
     * Filtres communs a la liste et aux indicateurs. Un `term_id` explicite
     * l'emporte sur `academic_year` (un trimestre appartient a une seule
     * annee) ; `month` borne en plus la date de saisie de la note.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Grade>
     */
    private function filtered(array $filters): Builder
    {
        $termId = $filters['term_id'] ?? null;

        return Grade::query()
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->where('grades.student_id', $id))
            ->when($filters['subject_id'] ?? null, fn ($query, $id) => $query->where('grades.subject_id', $id))
            ->when($termId, fn ($query, $id) => $query->where('grades.term_id', $id))
            ->when(
                ! $termId && ($filters['academic_year'] ?? null),
                fn ($query) => $query->whereHas('term', fn ($term) => $term->where('academic_year', $filters['academic_year'])),
            )
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('grades.type', $type))
            ->when($filters['school_class_id'] ?? null, fn ($query, $id) => $query->whereHas(
                'student',
                fn ($student) => $student->enrolledInClass($id),
            ))
            ->when($filters['month'] ?? null, fn ($query, $month) => Period::scope($query, ['month' => $month], 'grades.recorded_at'));
    }

    /** @return Collection<int, Grade> */
    public function forStudentAndTerm(string $studentId, string $termId): Collection
    {
        return Grade::query()
            ->with('subject')
            ->where('student_id', $studentId)
            ->where('term_id', $termId)
            ->get();
    }

    public function findOrFail(string $id): Grade
    {
        return Grade::query()->with(['student', 'subject', 'term'])->findOrFail($id);
    }

    public function create(array $attributes): Grade
    {
        return Grade::create($attributes);
    }

    public function update(Grade $grade, array $attributes): Grade
    {
        $grade->update($attributes);

        return $grade->refresh();
    }

    public function delete(Grade $grade): void
    {
        $grade->delete();
    }
}
