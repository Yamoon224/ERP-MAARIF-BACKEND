<?php

namespace App\Domains\Grades\Services;

use App\Domains\Grades\Contracts\GradeRepositoryContract;
use App\Models\Grade;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class GradeService
{
    public function __construct(private readonly GradeRepositoryContract $grades) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Grade>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->grades->paginate($filters, $perPage);
    }

    public function find(string $id): Grade
    {
        return $this->grades->findOrFail($id);
    }

    /** @param  array<string, mixed>  $data */
    public function record(array $data, string $teacherId): Grade
    {
        return $this->grades->create([...$data, 'teacher_id' => $teacherId]);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Grade $grade, array $data): Grade
    {
        return $this->grades->update($grade, $data);
    }

    public function delete(Grade $grade): void
    {
        $this->grades->delete($grade);
    }
}
