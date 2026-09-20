<?php

namespace App\Domains\Attendance\Repositories;

use App\Domains\Attendance\Contracts\AttendanceRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\AttendanceRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentAttendanceRepository implements AttendanceRepositoryContract
{
    /** @var array<string, string> */
    private const SORTABLE = [
        'date' => 'date',
    ];

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return AttendanceRecord::query()
            ->with('student:id,first_name,last_name,matricule')
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->where('student_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('date', '<=', $date))
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'date', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
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
}
