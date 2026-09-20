<?php

namespace App\Domains\Discipline\Repositories;

use App\Domains\Discipline\Contracts\SummonRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\Summon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentSummonRepository implements SummonRepositoryContract
{
    /** @var array<string, string> */
    private const SORTABLE = ['scheduled_at' => 'scheduled_at'];

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Summon::query()
            ->with('student:id,first_name,last_name,matricule')
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->where('student_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'scheduled_at', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(string $id): Summon
    {
        return Summon::query()->with('student')->findOrFail($id);
    }

    public function create(array $attributes): Summon
    {
        return Summon::create($attributes);
    }

    public function update(Summon $summon, array $attributes): Summon
    {
        $summon->update($attributes);

        return $summon->refresh();
    }

    public function delete(Summon $summon): void
    {
        $summon->delete();
    }
}
