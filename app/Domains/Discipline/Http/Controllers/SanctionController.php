<?php

namespace App\Domains\Discipline\Http\Controllers;

use App\Domains\Discipline\Http\Requests\StoreSanctionRequest;
use App\Domains\Discipline\Http\Requests\UpdateSanctionRequest;
use App\Domains\Discipline\Http\Resources\SanctionResource;
use App\Domains\Discipline\Services\SanctionService;
use App\Domains\Shared\Support\Period;
use App\Http\Controllers\Controller;
use App\Models\Sanction;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SanctionController extends Controller
{
    public function __construct(private readonly SanctionService $sanctions) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([...Period::rules(), 'school_class_id' => ['nullable', 'uuid']]);

        return SanctionResource::collection(
            $this->sanctions->list($request->only('student_id', 'type', 'school_class_id', 'academic_year', 'term_id', 'month', 'sort', 'direction'), $request->integer('per_page', 15)),
        );
    }

    public function store(StoreSanctionRequest $request): JsonResponse
    {
        $sanction = $this->sanctions->create($request->validated(), $request->user()->id);

        return (new SanctionResource($sanction->load('student')))->response()->setStatusCode(201);
    }

    public function show(Sanction $sanction): SanctionResource
    {
        return new SanctionResource($this->sanctions->find($sanction->id));
    }

    public function update(UpdateSanctionRequest $request, Sanction $sanction): SanctionResource
    {
        return new SanctionResource($this->sanctions->update($sanction, $request->validated())->load('student'));
    }

    public function destroy(Sanction $sanction): Response
    {
        $this->sanctions->delete($sanction);

        return response()->noContent();
    }

    /** Sanctions concernant son enfant, pour le portail parent. */
    public function mine(Request $request): AnonymousResourceCollection
    {
        $request->validate(Period::rules());

        /** @var Student $student */
        $student = $request->user();

        return SanctionResource::collection($this->sanctions->list(['student_id' => $student->id, ...$request->only('academic_year', 'term_id', 'month')], $request->integer('per_page', 15)));
    }
}
