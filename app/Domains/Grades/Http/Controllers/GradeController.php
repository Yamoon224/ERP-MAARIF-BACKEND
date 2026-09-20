<?php

namespace App\Domains\Grades\Http\Controllers;

use App\Domains\Grades\Http\Requests\StoreGradeRequest;
use App\Domains\Grades\Http\Requests\UpdateGradeRequest;
use App\Domains\Grades\Http\Resources\GradeResource;
use App\Domains\Grades\Services\GradeService;
use App\Http\Controllers\Controller;
use App\Models\Grade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Saisie des notes (cahier des charges 3.2), ouverte au personnel enseignant. */
class GradeController extends Controller
{
    public function __construct(private readonly GradeService $grades) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return GradeResource::collection(
            $this->grades->list(
                $request->only('student_id', 'subject_id', 'term_id', 'type', 'sort', 'direction'),
                $request->integer('per_page', 15),
            ),
        );
    }

    public function store(StoreGradeRequest $request): JsonResponse
    {
        $grade = $this->grades->record($request->validated(), $request->user()->id);

        return (new GradeResource($grade->load(['student', 'subject', 'term'])))->response()->setStatusCode(201);
    }

    public function show(Grade $grade): GradeResource
    {
        return new GradeResource($this->grades->find($grade->id));
    }

    public function update(UpdateGradeRequest $request, Grade $grade): GradeResource
    {
        return new GradeResource($this->grades->update($grade, $request->validated())->load(['student', 'subject', 'term']));
    }

    public function destroy(Grade $grade): Response
    {
        $this->grades->delete($grade);

        return response()->noContent();
    }
}
