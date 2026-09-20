<?php

namespace App\Domains\Attendance\Contracts;

use App\Models\AttendanceRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AttendanceRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, AttendanceRecord>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Cree ou met a jour l'enregistrement du jour pour cet eleve : une classe
     * pointee deux fois par erreur ne doit pas produire deux verdicts pour le
     * meme jour (voir la contrainte d'unicite en base).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function recordForDate(string $studentId, string $date, array $attributes): AttendanceRecord;

    public function findOrFail(string $id): AttendanceRecord;

    /** @param  array<string, mixed>  $attributes */
    public function update(AttendanceRecord $record, array $attributes): AttendanceRecord;

    public function delete(AttendanceRecord $record): void;

    /**
     * Bilan des presences sur la periode : effectifs par statut et eleves les
     * plus souvent absents.
     *
     * @param  array<string, mixed>  $filters  memes filtres que `paginate`
     * @return array{total: int, present: int, absent: int, late: int, justified_absences: int, unjustified_absences: int, top_absentees: list<array{student: array{id: string, name: string, matricule: string}, absences: int, unjustified: int, lates: int}>}
     */
    public function summary(array $filters = [], int $topAbsentees = 5): array;

    /**
     * Nombre d'absences (hors retards) par eleve sur la periode.
     *
     * @param  array<string, mixed>  $filters  memes filtres que `paginate`
     * @return array<string, int> identifiant d'eleve => absences
     */
    public function absenceCounts(array $filters = []): array;

    /**
     * Feuille d'appel : les eleves actifs de la classe, chacun avec son
     * pointage du jour (ou null s'il n'a pas encore ete pointe).
     *
     * @return Collection<int, array{student: \App\Models\Student, record: AttendanceRecord|null}>
     */
    public function rollCall(string $schoolClassId, string $date): Collection;

    /** @return list<string> */
    public function classStudentIds(string $schoolClassId): array;
}
