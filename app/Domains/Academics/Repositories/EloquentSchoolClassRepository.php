<?php

namespace App\Domains\Academics\Repositories;

use App\Domains\Academics\Contracts\SchoolClassRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\SchoolClass;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentSchoolClassRepository implements SchoolClassRepositoryContract
{
    /** @var array<string, string> */
    private const SORTABLE = [
        'name' => 'name',
        'level' => 'level',
        'academic_year' => 'academic_year',
    ];

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return SchoolClass::query()
            ->withCount('students')
            ->with('mainTeacher:id,name')
            ->when($filters['academic_year'] ?? null, fn ($query, $year) => $query->where('academic_year', $year))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'name', 'asc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(string $id): SchoolClass
    {
        return SchoolClass::query()->with(['mainTeacher', 'subjects'])->findOrFail($id);
    }

    public function create(array $attributes): SchoolClass
    {
        return SchoolClass::create($attributes);
    }

    public function update(SchoolClass $schoolClass, array $attributes): SchoolClass
    {
        $schoolClass->update($attributes);

        return $schoolClass->refresh();
    }

    public function delete(SchoolClass $schoolClass): void
    {
        $schoolClass->delete();
    }
}
