<?php

namespace App\Domains\Attendance\Services;

use App\Domains\Attendance\Contracts\AttendanceRepositoryContract;
use App\Models\AttendanceRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
