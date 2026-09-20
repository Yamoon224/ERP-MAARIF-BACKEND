<?php

namespace App\Domains\Grades\Repositories;

use App\Domains\Grades\Contracts\GradeRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\Grade;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        return Grade::query()
            ->with(['student:id,first_name,last_name,matricule', 'subject:id,name,code', 'term:id,name'])
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->where('student_id', $id))
            ->when($filters['subject_id'] ?? null, fn ($query, $id) => $query->where('subject_id', $id))
            ->when($filters['term_id'] ?? null, fn ($query, $id) => $query->where('term_id', $id))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'recorded_at', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
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
