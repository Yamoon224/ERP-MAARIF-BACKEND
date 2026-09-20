<?php

namespace App\Domains\Academics\Repositories;

use App\Domains\Academics\Contracts\SubjectRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\Subject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
}
