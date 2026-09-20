<?php

namespace App\Domains\Accounting\Http\Controllers;

use App\Domains\Accounting\Services\AccountingReportService;
use App\Domains\Shared\Support\Period;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingReportController extends Controller
{
    public function __construct(private readonly AccountingReportService $reports) {}

    public function summary(Request $request): JsonResponse
    {
        $request->validate([...Period::rules(), 'school_class_id' => ['nullable', 'uuid']]);

        return response()->json([
            'data' => $this->reports->summary($request->only('academic_year', 'term_id', 'month', 'school_class_id')),
        ]);
    }

    /** Eleves en retard de paiement, les plus endettes d'abord. */
    public function arrears(Request $request): JsonResponse
    {
        $request->validate([...Period::rules(), 'school_class_id' => ['nullable', 'uuid']]);

        $page = $this->reports->arrears(
            $request->only('academic_year', 'term_id', 'month', 'school_class_id', 'search'),
            $request->integer('per_page', 15),
        );

        return response()->json([
            'data' => $page->getCollection()->map(fn ($row) => [
                'enrollment_id' => $row->enrollment_id,
                'student' => [
                    'id' => $row->enrollment->student->id,
                    'name' => $row->enrollment->student->fullName(),
                    'matricule' => $row->enrollment->student->matricule,
                    'guardian_phone' => $row->enrollment->student->guardian_phone,
                ],
                'academic_year' => $row->enrollment->academic_year,
                'school_class' => $row->enrollment->schoolClass?->name,
                'months_overdue' => (int) $row->months_overdue,
                'amount' => round((float) $row->total_amount, 2),
                'oldest_month' => substr((string) $row->oldest_month, 0, 7),
            ])->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
