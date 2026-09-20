<?php

namespace App\Domains\Admissions\Contracts;

use App\Models\AdmissionApplication;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AdmissionRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters  `search`, `status`, `academic_year`, `level`, `sort`, `direction`
     * @return LengthAwarePaginator<int, AdmissionApplication>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findOrFail(string $id): AdmissionApplication;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): AdmissionApplication;

    /** @param  array<string, mixed>  $attributes */
    public function update(AdmissionApplication $application, array $attributes): AdmissionApplication;

    public function delete(AdmissionApplication $application): void;

    /**
     * Nombre de dossiers par statut (les statuts sans dossier sont absents).
     *
     * @return Collection<string, int> valeur du statut => nombre
     */
    public function countByStatus(?string $academicYear = null): Collection;
}
