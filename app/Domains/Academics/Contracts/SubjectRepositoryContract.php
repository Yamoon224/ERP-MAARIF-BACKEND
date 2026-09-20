<?php

namespace App\Domains\Academics\Contracts;

use App\Models\Subject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SubjectRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Subject>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findOrFail(string $id): Subject;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Subject;

    /** @param  array<string, mixed>  $attributes */
    public function update(Subject $subject, array $attributes): Subject;

    public function delete(Subject $subject): void;
}
