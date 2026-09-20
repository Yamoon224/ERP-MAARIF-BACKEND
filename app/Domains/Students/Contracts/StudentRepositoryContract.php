<?php

namespace App\Domains\Students\Contracts;

use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface StudentRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Student>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findOrFail(string $id): Student;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Student;

    /** @param  array<string, mixed>  $attributes */
    public function update(Student $student, array $attributes): Student;

    public function delete(Student $student): void;
}
