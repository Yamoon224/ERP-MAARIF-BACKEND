<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Http\Requests\StoreTermRequest;
use App\Domains\Academics\Http\Requests\UpdateTermRequest;
use App\Domains\Academics\Http\Resources\TermResource;
use App\Domains\Academics\Services\TermService;
use App\Http\Controllers\Controller;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TermController extends Controller
{
    public function __construct(private readonly TermService $terms) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return TermResource::collection(
            $this->terms->list($request->only('academic_year', 'sort', 'direction'), $request->integer('per_page', 15)),
        );
    }

    public function all(): AnonymousResourceCollection
    {
        return TermResource::collection($this->terms->all());
    }

    public function store(StoreTermRequest $request): JsonResponse
    {
        $term = $this->terms->create($request->validated());

        return (new TermResource($term))->response()->setStatusCode(201);
    }

    public function show(Term $term): TermResource
    {
        return new TermResource($term);
    }

    public function update(UpdateTermRequest $request, Term $term): TermResource
    {
        return new TermResource($this->terms->update($term, $request->validated()));
    }

    public function destroy(Term $term): Response
    {
        $this->terms->delete($term);

        return response()->noContent();
    }
}
