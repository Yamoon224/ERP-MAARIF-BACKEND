<?php

namespace App\Domains\Academics\Services;

use App\Domains\Academics\Contracts\SubjectRepositoryContract;
use App\Models\Subject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SubjectService
{
    public function __construct(private readonly SubjectRepositoryContract $subjects) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Subject>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->subjects->paginate($filters, $perPage);
    }

    public function find(string $id): Subject
    {
        return $this->subjects->findOrFail($id);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Subject
    {
        return $this->subjects->create($data);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Subject $subject, array $data): Subject
    {
        return $this->subjects->update($subject, $data);
    }

    public function delete(Subject $subject): void
    {
        $this->subjects->delete($subject);
    }
}
