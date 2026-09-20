<?php

namespace App\Domains\Academics\Contracts;

use App\Models\SchoolClass;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SchoolClassRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, SchoolClass>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findOrFail(string $id): SchoolClass;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): SchoolClass;

    /** @param  array<string, mixed>  $attributes */
    public function update(SchoolClass $schoolClass, array $attributes): SchoolClass;

    public function delete(SchoolClass $schoolClass): void;
}
