<?php

namespace App\Domains\Discipline\Contracts;

use App\Models\Sanction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SanctionRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Sanction>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findOrFail(string $id): Sanction;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Sanction;

    /** @param  array<string, mixed>  $attributes */
    public function update(Sanction $sanction, array $attributes): Sanction;

    public function delete(Sanction $sanction): void;
}
