<?php

namespace App\Domains\Academics\Contracts;

use App\Models\Subject;
use App\Models\Term;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

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

    /**
     * Matieres actives pendant un trimestre : celles enseignees dans les
     * classes de l'annee du trimestre, plus celles qui ont deja des notes ce
     * trimestre, avec leurs statistiques.
     *
     * @return Collection<int, array{id: string, name: string, code: string, coefficient: float, classes_count: int, grades_count: int, average: float|null}>
     */
    public function forTerm(Term $term): Collection;
}
