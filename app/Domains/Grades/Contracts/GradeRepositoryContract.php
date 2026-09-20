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

    /**
     * @param  array<string, mixed>  $filters  memes filtres que `paginate`
     */
    public function count(array $filters = []): int;

    /**
     * Moyenne generale (ponderee par les coefficients) de chaque eleve ayant
     * des notes, sur la periode demandee.
     *
     * @param  array<string, mixed>  $filters  memes filtres que `paginate`
     * @return Collection<string, float> identifiant d'eleve => moyenne sur 20
     */
    public function studentAverages(array $filters = []): Collection;
}
