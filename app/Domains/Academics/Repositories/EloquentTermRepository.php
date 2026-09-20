<?php

namespace App\Domains\Academics\Repositories;

use App\Domains\Academics\Contracts\TermRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\Term;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EloquentTermRepository implements TermRepositoryContract
{
    /** @var array<string, string> */
    private const SORTABLE = [
        'name' => 'name',
        'starts_at' => 'starts_at',
    ];

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Term::query()
            ->when($filters['academic_year'] ?? null, fn ($query, $year) => $query->where('academic_year', $year))
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'starts_at', 'asc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(): Collection
    {
        return Term::query()->orderBy('starts_at')->get();
    }

    public function findOrFail(string $id): Term
    {
        return Term::query()->findOrFail($id);
    }

    public function create(array $attributes): Term
    {
        return Term::create($attributes);
    }

    public function update(Term $term, array $attributes): Term
    {
        $term->update($attributes);

        return $term->refresh();
    }

    public function delete(Term $term): void
    {
        $term->delete();
    }

    public function clearCurrentExcept(Term $term): void
    {
        Term::query()->where('id', '!=', $term->id)->update(['is_current' => false]);
    }
}
