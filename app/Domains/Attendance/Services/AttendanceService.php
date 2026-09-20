<?php

namespace App\Domains\Attendance\Services;

use App\Domains\Attendance\Contracts\AttendanceRepositoryContract;
use App\Domains\Attendance\Exceptions\AttendanceException;
use App\Models\AttendanceRecord;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AttendanceService
{
    public function __construct(private readonly AttendanceRepositoryContract $records) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, AttendanceRecord>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->records->paginate($filters, $perPage);
    }

    /** @param  array<string, mixed>  $data */
    public function record(array $data, string $recordedByUserId): AttendanceRecord
    {
        return $this->records->recordForDate(
            $data['student_id'],
            $data['date'],
            [
                'status' => $data['status'],
                'justified' => $data['justified'] ?? false,
                'reason' => $data['reason'] ?? null,
                'recorded_by' => $recordedByUserId,
            ],
        );
    }

    /**
     * Appel d'une classe : enregistre le pointage de plusieurs eleves pour une
     * date, en un seul bloc. Tout ou rien : un appel a moitie enregistre
     * laisserait la feuille d'une classe dans un etat que personne n'a saisi.
     *
     * @param  list<array<string, mixed>>  $records
     * @return EloquentCollection<int, AttendanceRecord>
     */
    public function recordClass(string $schoolClassId, string $date, array $records, string $recordedByUserId): EloquentCollection
    {
        $classStudents = array_flip($this->records->classStudentIds($schoolClassId));

        foreach ($records as $record) {
            if (! isset($classStudents[$record['student_id']])) {
                throw AttendanceException::studentNotInClass();
            }
        }

        $saved = DB::transaction(fn () => array_map(
            fn (array $record) => $this->record([...$record, 'date' => $date], $recordedByUserId),
            $records,
        ));

        return (new EloquentCollection($saved))->load('student:id,first_name,last_name,matricule');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        return $this->records->summary($filters);
    }

    /** @return Collection<int, array{student: Student, record: AttendanceRecord|null}> */
    public function rollCall(string $schoolClassId, string $date): Collection
    {
        return $this->records->rollCall($schoolClassId, $date);
    }

    /** @param  array<string, mixed>  $data */
    public function update(AttendanceRecord $record, array $data): AttendanceRecord
    {
        return $this->records->update($record, $data);
    }

    public function delete(AttendanceRecord $record): void
    {
        $this->records->delete($record);
    }
}
