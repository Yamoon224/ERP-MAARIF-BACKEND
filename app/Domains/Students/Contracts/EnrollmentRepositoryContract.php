<?php

namespace App\Domains\Students\Contracts;

use App\Models\Enrollment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface EnrollmentRepositoryContract
{
    /** Historique d'un eleve, annee la plus recente en premier.
     *
     * @return Collection<int, Enrollment>
     */
    public function forStudent(string $studentId): Collection;

    public function findOrFail(string $id): Enrollment;

    /**
     * Inscrit l'eleve pour l'annee, ou change sa classe s'il l'est deja : un
     * eleve n'a qu'une inscription par annee scolaire (contrainte en base).
     */
    public function upsertForYear(string $studentId, string $academicYear, ?string $schoolClassId, string $enrolledOn): Enrollment;

    /**
     * Inscriptions d'une annee scolaire, avec l'eleve et la classe.
     *
     * @param  array<string, mixed>  $filters  `search`, `school_class_id`
     * @return LengthAwarePaginator<int, Enrollment>
     */
    public function paginateForYear(string $academicYear, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function countForYear(string $academicYear, ?string $schoolClassId = null): int;
}
