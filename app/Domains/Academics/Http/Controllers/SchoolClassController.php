<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Http\Requests\StoreSchoolClassRequest;
use App\Domains\Academics\Http\Requests\UpdateSchoolClassRequest;
use App\Domains\Academics\Http\Resources\SchoolClassResource;
use App\Domains\Academics\Services\SchoolClassService;
use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SchoolClassController extends Controller
{
    public function __construct(private readonly SchoolClassService $classes) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return SchoolClassResource::collection(
            $this->classes->list(
                $request->only('search', 'academic_year', 'sort', 'direction'),
                $request->integer('per_page', 15),
            ),
        );
    }

    public function store(StoreSchoolClassRequest $request): JsonResponse
    {
        $schoolClass = $this->classes->create($request->validated());

        return (new SchoolClassResource($schoolClass))->response()->setStatusCode(201);
    }

    public function show(SchoolClass $schoolClass): SchoolClassResource
    {
        return new SchoolClassResource($this->classes->find($schoolClass->id));
    }

    public function update(UpdateSchoolClassRequest $request, SchoolClass $schoolClass): SchoolClassResource
    {
        return new SchoolClassResource($this->classes->update($schoolClass, $request->validated()));
    }

    public function destroy(SchoolClass $schoolClass): Response
    {
        $this->classes->delete($schoolClass);

        return response()->noContent();
    }
}
