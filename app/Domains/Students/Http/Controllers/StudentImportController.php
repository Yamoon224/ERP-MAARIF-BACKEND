<?php

namespace App\Domains\Students\Http\Controllers;

use App\Domains\Students\Http\Requests\ImportStudentsRequest;
use App\Domains\Students\Services\StudentImportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** Import en masse d'eleves depuis un tableur (CSV/Excel), pour migrer les donnees d'un systeme existant. */
class StudentImportController extends Controller
{
    public function __construct(private readonly StudentImportService $imports) {}

    public function import(ImportStudentsRequest $request): JsonResponse
    {
        $dryRun = $request->boolean('dry_run', true);

        return response()->json(['data' => $this->imports->import($request->file('file'), $dryRun)]);
    }
}
