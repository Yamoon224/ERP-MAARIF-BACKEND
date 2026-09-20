<?php

namespace App\Domains\Attendance\Contracts;

use App\Models\AttendanceRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
}
