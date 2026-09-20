<?php

namespace App\Domains\Attendance\Http\Controllers;

use App\Domains\Attendance\Http\Requests\StoreAttendanceRequest;
use App\Domains\Attendance\Http\Requests\UpdateAttendanceRequest;
use App\Domains\Attendance\Http\Resources\AttendanceResource;
use App\Domains\Attendance\Services\AttendanceService;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Presences et absences (cahier des charges 3.2), saisies par le personnel. */
class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return AttendanceResource::collection(
            $this->attendance->list(
                $request->only('student_id', 'status', 'date_from', 'date_to', 'sort', 'direction'),
                $request->integer('per_page', 15),
            ),
        );
    }

    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $record = $this->attendance->record($request->validated(), $request->user()->id);

        return (new AttendanceResource($record->load('student')))->response()->setStatusCode(201);
    }

    public function update(UpdateAttendanceRequest $request, AttendanceRecord $attendanceRecord): AttendanceResource
    {
        return new AttendanceResource($this->attendance->update($attendanceRecord, $request->validated())->load('student'));
    }

    public function destroy(AttendanceRecord $attendanceRecord): Response
    {
        $this->attendance->delete($attendanceRecord);

        return response()->noContent();
    }

    /** Historique de presence de son enfant, pour le portail parent. */
    public function mine(Request $request): AnonymousResourceCollection
    {
        /** @var Student $student */
        $student = $request->user();

        return AttendanceResource::collection(
            $this->attendance->list(['student_id' => $student->id, ...$request->only('status', 'date_from', 'date_to')], $request->integer('per_page', 15)),
        );
    }
}
