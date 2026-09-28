<?php

namespace App\Domains\Results\Http\Controllers;

use App\Domains\Results\Http\Requests\DuplicateGradeScaleRequest;
use App\Domains\Results\Http\Requests\SaveGradeScaleRequest;
use App\Domains\Results\Http\Resources\GradeScaleBandResource;
use App\Domains\Results\Services\GradeScaleService;
use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Bareme de passage et d'appreciation d'une classe, configurable par
 * l'administration (cahier des charges - decision de passage automatique).
 */
class GradeScaleController extends Controller
{
    public function __construct(private readonly GradeScaleService $gradeScale) {}

    public function show(SchoolClass $schoolClass): AnonymousResourceCollection
    {
        return GradeScaleBandResource::collection($this->gradeScale->forClass($schoolClass->id));
    }

    public function update(SaveGradeScaleRequest $request, SchoolClass $schoolClass): AnonymousResourceCollection
    {
        return GradeScaleBandResource::collection($this->gradeScale->save($schoolClass->id, $request->validated('bands')));
    }

    /** Copie le bareme de cette classe vers d'autres, pour ne pas le ressaisir classe par classe. */
    public function duplicate(DuplicateGradeScaleRequest $request, SchoolClass $schoolClass): JsonResponse
    {
        $this->gradeScale->duplicate($schoolClass->id, $request->validated('target_class_ids'));

        return response()->json(['data' => ['duplicated' => true]]);
    }
}
