<?php

namespace App\Domains\Academics\Repositories;

use App\Domains\Academics\Contracts\SubjectRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentSubjectRepository implements SubjectRepositoryContract
{
    /** @var array<string, string> */
    private const SORTABLE = [
        'name' => 'name',
        'code' => 'code',
        'coefficient' => 'coefficient',
    ];

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Subject::query()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($sub) => $sub->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"),
            ))
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'name', 'asc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(string $id): Subject
    {
        return Subject::query()->findOrFail($id);
    }

    public function create(array $attributes): Subject
    {
        return Subject::create($attributes);
    }

    public function update(Subject $subject, array $attributes): Subject
    {
        $subject->update($attributes);

        return $subject->refresh();
    }

    public function delete(Subject $subject): void
    {
        $subject->delete();
    }

    public function forTerm(Term $term): Collection
    {
        $gradeStats = Grade::query()
            ->where('term_id', $term->id)
            ->groupBy('subject_id')
            ->selectRaw('subject_id, count(*) as grades_count, avg(value * 20.0 / nullif(max_value, 0)) as average')
            ->get()
            ->keyBy('subject_id');

        $classCounts = DB::table('class_subject_teacher')
            ->join('school_classes', 'school_classes.id', '=', 'class_subject_teacher.school_class_id')
            ->where('school_classes.academic_year', $term->academic_year)
            ->groupBy('class_subject_teacher.subject_id')
            ->selectRaw('class_subject_teacher.subject_id as subject_id, count(*) as classes_count')
            ->pluck('classes_count', 'subject_id');

        $subjectIds = $gradeStats->keys()->merge($classCounts->keys())->unique()->values();

        return Subject::query()
            ->whereIn('id', $subjectIds)
            ->orderBy('name')
            ->get()
            ->map(fn (Subject $subject) => [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
                'coefficient' => (float) $subject->coefficient,
                'classes_count' => (int) ($classCounts[$subject->id] ?? 0),
                'grades_count' => (int) ($gradeStats[$subject->id]->grades_count ?? 0),
                'average' => isset($gradeStats[$subject->id]->average) ? round((float) $gradeStats[$subject->id]->average, 2) : null,
            ]);
    }
}
