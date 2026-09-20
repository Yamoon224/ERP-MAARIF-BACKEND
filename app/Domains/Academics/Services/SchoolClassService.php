<?php

namespace App\Domains\Academics\Services;

use App\Domains\Academics\Contracts\SchoolClassRepositoryContract;
use App\Models\SchoolClass;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SchoolClassService
{
    public function __construct(private readonly SchoolClassRepositoryContract $classes) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, SchoolClass>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->classes->paginate($filters, $perPage);
    }

    public function find(string $id): SchoolClass
    {
        return $this->classes->findOrFail($id);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): SchoolClass
    {
        return $this->classes->create($data);
    }

    /** @param  array<string, mixed>  $data */
    public function update(SchoolClass $schoolClass, array $data): SchoolClass
    {
        return $this->classes->update($schoolClass, $data);
    }

    public function delete(SchoolClass $schoolClass): void
    {
        $this->classes->delete($schoolClass);
    }
}
