<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Http\Requests\StoreSubjectRequest;
use App\Domains\Academics\Http\Requests\UpdateSubjectRequest;
use App\Domains\Academics\Http\Resources\SubjectResource;
use App\Domains\Academics\Services\SubjectService;
use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SubjectController extends Controller
{
    public function __construct(private readonly SubjectService $subjects) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return SubjectResource::collection(
            $this->subjects->list(
                $request->only('search', 'sort', 'direction'),
                $request->integer('per_page', 15),
            ),
        );
    }

    public function store(StoreSubjectRequest $request): JsonResponse
    {
        $subject = $this->subjects->create($request->validated());

        return (new SubjectResource($subject))->response()->setStatusCode(201);
    }

    public function show(Subject $subject): SubjectResource
    {
        return new SubjectResource($subject);
    }

    public function update(UpdateSubjectRequest $request, Subject $subject): SubjectResource
    {
        return new SubjectResource($this->subjects->update($subject, $request->validated()));
    }

    public function destroy(Subject $subject): Response
    {
        $this->subjects->delete($subject);

        return response()->noContent();
    }
}
