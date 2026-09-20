<?php

namespace App\Domains\Attendance\Http\Controllers;

use App\Domains\Attendance\Http\Requests\BulkAttendanceRequest;
use App\Domains\Attendance\Http\Requests\StoreAttendanceRequest;
use App\Domains\Attendance\Http\Requests\UpdateAttendanceRequest;
use App\Domains\Attendance\Http\Resources\AttendanceResource;
use App\Domains\Attendance\Services\AttendanceService;
use App\Domains\Shared\Support\Period;
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
    private const FILTERS = [
        'student_id', 'school_class_id', 'status', 'justified',
        'date_from', 'date_to', 'academic_year', 'term_id', 'month',
    ];

    public function __construct(private readonly AttendanceService $attendance) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->validateFilters($request);

        return AttendanceResource::collection(
            $this->attendance->list(
                $request->only([...self::FILTERS, 'sort', 'direction']),
                $request->integer('per_page', 15),
            ),
        );
    }

    /** Bilan des absences et retards sur la periode filtree. */
    public function summary(Request $request): JsonResponse
    {
        $this->validateFilters($request);

        return response()->json(['data' => $this->attendance->summary($request->only(self::FILTERS))]);
    }

    /** Feuille d'appel d'une classe pour une date. */
    public function rollCall(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_class_id' => ['required', 'uuid'],
            'date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $rows = $this->attendance->rollCall($validated['school_class_id'], $validated['date']);

        return response()->json([
            'data' => $rows->map(fn (array $row) => [
                'student' => [
                    'id' => $row['student']->id,
                    'name' => $row['student']->fullName(),
                    'matricule' => $row['student']->matricule,
                ],
                'record' => $row['record'] === null ? null : [
                    'id' => $row['record']->id,
                    'status' => $row['record']->status->value,
                    'justified' => $row['record']->justified,
                    'reason' => $row['record']->reason,
                ],
            ])->values(),
        ]);
    }

    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $record = $this->attendance->record($request->validated(), $request->user()->id);

        return (new AttendanceResource($record->load('student')))->response()->setStatusCode(201);
    }

    /** Enregistre l'appel d'une classe entiere. */
    public function storeBulk(BulkAttendanceRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        return AttendanceResource::collection(
            $this->attendance->recordClass(
                $validated['school_class_id'],
                $validated['date'],
                $validated['records'],
                $request->user()->id,
            ),
        );
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
        $this->validateFilters($request);

        /** @var Student $student */
        $student = $request->user();

        return AttendanceResource::collection(
            $this->attendance->list(
                ['student_id' => $student->id, ...$request->only('status', 'date_from', 'date_to', 'academic_year', 'term_id', 'month')],
                $request->integer('per_page', 15),
            ),
        );
    }

    private function validateFilters(Request $request): void
    {
        $request->validate([
            ...Period::rules(),
            'school_class_id' => ['nullable', 'uuid'],
            'justified' => ['nullable', 'boolean'],
        ]);
    }
}
