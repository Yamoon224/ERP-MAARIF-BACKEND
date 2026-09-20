<?php

namespace App\Domains\Grades\Contracts;

use App\Models\Grade;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface GradeRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Grade>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /** @return Collection<int, Grade> */
    public function forStudentAndTerm(string $studentId, string $termId): Collection;

    public function findOrFail(string $id): Grade;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Grade;

    /** @param  array<string, mixed>  $attributes */
    public function update(Grade $grade, array $attributes): Grade;

    public function delete(Grade $grade): void;
}
