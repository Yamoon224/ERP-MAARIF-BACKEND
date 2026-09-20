<?php

namespace App\Domains\Discipline\Repositories;

use App\Domains\Discipline\Contracts\SanctionRepositoryContract;
use App\Domains\Shared\Support\Period;
use App\Domains\Shared\Support\Sort;
use App\Models\Sanction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class EloquentSanctionRepository implements SanctionRepositoryContract
{
    /** @var array<string, string> */
    private const SORTABLE = ['start_date' => 'start_date'];

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->with('student:id,first_name,last_name,matricule')
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'start_date', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function count(array $filters = []): int
    {
        return $this->filtered($filters)->count();
    }

    public function findOrFail(string $id): Sanction
    {
        return Sanction::query()->with('student')->findOrFail($id);
    }

    public function create(array $attributes): Sanction
    {
        return Sanction::create($attributes);
    }

    public function update(Sanction $sanction, array $attributes): Sanction
    {
        $sanction->update($attributes);

        return $sanction->refresh();
    }

    public function delete(Sanction $sanction): void
    {
        $sanction->delete();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Sanction>
     */
    private function filtered(array $filters): Builder
    {
        return Period::scope(Sanction::query(), $filters, 'start_date')
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->where('student_id', $id))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['school_class_id'] ?? null, fn ($query, $id) => $query->whereHas(
                'student',
                fn ($student) => $student->enrolledInClass($id),
            ));
    }
}
