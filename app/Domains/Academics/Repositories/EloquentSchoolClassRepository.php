<?php

namespace App\Domains\Academics\Repositories;

use App\Domains\Academics\Contracts\SchoolClassRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\SchoolClass;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

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
            // Effectif = eleves inscrits dans la classe, pas ceux qui y sont
            // "actuellement" : une classe d'une annee passee garde son effectif
            // meme apres le passage de ses eleves en classe superieure.
            ->withCount('enrollments as students_count')
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

    public function academicYears(): Collection
    {
        return SchoolClass::query()->distinct()->orderByDesc('academic_year')->pluck('academic_year');
    }

    public function count(array $filters = []): int
    {
        return SchoolClass::query()
            ->when($filters['academic_year'] ?? null, fn ($query, $year) => $query->where('academic_year', $year))
            ->count();
    }
}
