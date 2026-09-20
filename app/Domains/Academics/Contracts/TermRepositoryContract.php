<?php

namespace App\Domains\Academics\Contracts;

use App\Models\Term;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TermRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Term>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /** @return Collection<int, Term> */
    public function all(): Collection;

    public function findOrFail(string $id): Term;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Term;

    /** @param  array<string, mixed>  $attributes */
    public function update(Term $term, array $attributes): Term;

    public function delete(Term $term): void;

    /** Marque tous les autres trimestres comme non courants. */
    public function clearCurrentExcept(Term $term): void;
}
