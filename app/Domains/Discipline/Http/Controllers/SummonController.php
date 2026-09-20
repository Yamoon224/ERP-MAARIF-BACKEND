<?php

namespace App\Domains\Discipline\Http\Controllers;

use App\Domains\Discipline\Http\Requests\StoreSummonRequest;
use App\Domains\Discipline\Http\Requests\UpdateSummonRequest;
use App\Domains\Discipline\Http\Resources\SummonResource;
use App\Domains\Discipline\Services\SummonService;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Summon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SummonController extends Controller
{
    public function __construct(private readonly SummonService $summons) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return SummonResource::collection(
            $this->summons->list($request->only('student_id', 'status', 'sort', 'direction'), $request->integer('per_page', 15)),
        );
    }

    public function store(StoreSummonRequest $request): JsonResponse
    {
        $summon = $this->summons->create($request->validated(), $request->user()->id);

        return (new SummonResource($summon->load('student')))->response()->setStatusCode(201);
    }

    public function show(Summon $summon): SummonResource
    {
        return new SummonResource($this->summons->find($summon->id));
    }

    public function update(UpdateSummonRequest $request, Summon $summon): SummonResource
    {
        return new SummonResource($this->summons->update($summon, $request->validated())->load('student'));
    }

    public function destroy(Summon $summon): Response
    {
        $this->summons->delete($summon);

        return response()->noContent();
    }

    /** Convocations concernant son enfant, pour le portail parent. */
    public function mine(Request $request): AnonymousResourceCollection
    {
        /** @var Student $student */
        $student = $request->user();

        return SummonResource::collection($this->summons->list(['student_id' => $student->id], $request->integer('per_page', 15)));
    }
}
