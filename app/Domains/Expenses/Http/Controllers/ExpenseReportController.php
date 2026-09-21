<?php

namespace App\Domains\Expenses\Http\Controllers;

use App\Domains\Expenses\Services\ExpenseReportService;
use App\Domains\Shared\Support\Period;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseReportController extends Controller
{
    public function __construct(private readonly ExpenseReportService $reports) {}

    public function summary(Request $request): JsonResponse
    {
        $request->validate(Period::rules());

        return response()->json([
            'data' => $this->reports->summary($request->only('academic_year', 'term_id', 'month')),
        ]);
    }
}
