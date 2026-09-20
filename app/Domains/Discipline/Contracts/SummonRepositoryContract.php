<?php

namespace App\Domains\Discipline\Contracts;

use App\Models\Summon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SummonRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Summon>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findOrFail(string $id): Summon;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Summon;

    /** @param  array<string, mixed>  $attributes */
    public function update(Summon $summon, array $attributes): Summon;

    public function delete(Summon $summon): void;

    /** @param  array<string, mixed>  $filters  memes filtres que `paginate` */
    public function count(array $filters = []): int;
}
