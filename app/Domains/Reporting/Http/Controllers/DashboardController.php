<?php

namespace App\Domains\Reporting\Http\Controllers;

use App\Domains\Reporting\Services\DashboardService;
use App\Domains\Shared\Support\Period;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(Period::rules());

        $user = $request->user();

        return response()->json([
            'data' => $this->dashboard->stats(
                $request->only('academic_year', 'term_id', 'month'),
                includeDiscipline: $user->can('discipline.manage'),
                includeAccounting: $user->can('accounting.view'),
            ),
        ]);
    }
}
