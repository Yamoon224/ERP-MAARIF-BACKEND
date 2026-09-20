<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Services\AcademicYearService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AcademicYearController extends Controller
{
    public function __construct(private readonly AcademicYearService $years) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->years->all()->values()]);
    }
}
