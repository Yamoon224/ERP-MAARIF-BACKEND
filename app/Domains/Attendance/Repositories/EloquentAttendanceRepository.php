<?php

namespace App\Domains\Attendance\Repositories;

use App\Domains\Attendance\Contracts\AttendanceRepositoryContract;
use App\Domains\Attendance\Enums\AttendanceStatus;
use App\Domains\Shared\Support\Period;
use App\Domains\Shared\Support\Sort;
use App\Models\AttendanceRecord;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EloquentAttendanceRepository implements AttendanceRepositoryContract
{
    /** @var array<string, string> */
    private const SORTABLE = [
        'date' => 'date',
    ];

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->with('student:id,first_name,last_name,matricule')
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'date', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function summary(array $filters = [], int $topAbsentees = 5): array
    {
        $byStatus = $this->filtered($filters)
            ->selectRaw('status, justified, count(*) as total')
            ->groupBy('status', 'justified')
            ->get();

        $count = fn (AttendanceStatus $status, ?bool $justified = null): int => (int) $byStatus
            ->filter(fn ($row) => $row->status === $status && ($justified === null || (bool) $row->justified === $justified))
            ->sum('total');

        $absences = $this->filtered($filters)
            ->whereIn('status', [AttendanceStatus::Absent->value, AttendanceStatus::Late->value])
            ->selectRaw(
                "student_id,
                 sum(case when status = 'absent' then 1 else 0 end) as absences,
                 sum(case when status = 'absent' and justified = ? then 1 else 0 end) as unjustified,
                 sum(case when status = 'retard' then 1 else 0 end) as lates",
                [false],
            )
            ->groupBy('student_id')
            ->orderByDesc('absences')
            ->orderByDesc('lates')
            ->limit($topAbsentees)
            ->get();

        $students = Student::query()
            ->whereIn('id', $absences->pluck('student_id'))
            ->get(['id', 'first_name', 'last_name', 'matricule'])
            ->keyBy('id');

        return [
            'total' => (int) $byStatus->sum('total'),
            'present' => $count(AttendanceStatus::Present),
            'absent' => $count(AttendanceStatus::Absent),
            'late' => $count(AttendanceStatus::Late),
            'justified_absences' => $count(AttendanceStatus::Absent, true),
            'unjustified_absences' => $count(AttendanceStatus::Absent, false),
            'top_absentees' => $absences->map(fn ($row) => [
                'student' => [
                    'id' => $row->student_id,
                    'name' => $students[$row->student_id]->fullName(),
                    'matricule' => $students[$row->student_id]->matricule,
                ],
                'absences' => (int) $row->absences,
                'unjustified' => (int) $row->unjustified,
                'lates' => (int) $row->lates,
            ])->values()->all(),
        ];
    }

    public function rollCall(string $schoolClassId, string $date): Collection
    {
        $students = Student::query()
            ->enrolledInClass($schoolClassId)
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'matricule']);

        $records = AttendanceRecord::query()
            ->whereDate('date', $date)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy('student_id');

        return $students->map(fn (Student $student) => [
            'student' => $student,
            'record' => $records->get($student->id),
        ]);
    }

    public function classStudentIds(string $schoolClassId): array
    {
        return Student::query()->enrolledInClass($schoolClassId)->pluck('id')->all();
    }

    public function recordForDate(string $studentId, string $date, array $attributes): AttendanceRecord
    {
        return AttendanceRecord::updateOrCreate(
            ['student_id' => $studentId, 'date' => $date],
            $attributes,
        );
    }

    public function findOrFail(string $id): AttendanceRecord
    {
        return AttendanceRecord::query()->with('student')->findOrFail($id);
    }

    public function update(AttendanceRecord $record, array $attributes): AttendanceRecord
    {
        $record->update($attributes);

        return $record->refresh();
    }

    public function delete(AttendanceRecord $record): void
    {
        $record->delete();
    }

    /**
     * Filtres communs a la liste et au bilan : eleve, statut, justification,
     * classe, intervalle explicite (`date_from`/`date_to`) et periode
     * scolaire (annee, trimestre ou mois).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<AttendanceRecord>
     */
    private function filtered(array $filters): Builder
    {
        $justified = $filters['justified'] ?? null;

        return Period::scope(AttendanceRecord::query(), $filters, 'date')
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->where('student_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($justified !== null && $justified !== '', fn ($query) => $query->where('justified', filter_var($justified, FILTER_VALIDATE_BOOLEAN)))
            ->when($filters['school_class_id'] ?? null, fn ($query, $id) => $query->whereHas(
                'student',
                fn ($student) => $student->enrolledInClass($id),
            ))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('date', '<=', $date));
    }
}
