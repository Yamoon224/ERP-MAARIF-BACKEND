<?php

namespace App\Domains\Admissions\Http\Controllers;

use App\Domains\Admissions\Enums\AdmissionStatus;
use App\Domains\Admissions\Http\Requests\ChangeAdmissionStatusRequest;
use App\Domains\Admissions\Http\Requests\EnrollAdmissionRequest;
use App\Domains\Admissions\Http\Requests\StoreAdmissionRequest;
use App\Domains\Admissions\Http\Requests\UpdateAdmissionRequest;
use App\Domains\Admissions\Http\Resources\AdmissionResource;
use App\Domains\Admissions\Services\AdmissionService;
use App\Domains\Students\Http\Resources\StudentResource;
use App\Http\Controllers\Controller;
use App\Models\AdmissionApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Candidatures d'admission : depot, instruction et inscription. */
class AdmissionController extends Controller
{
    public function __construct(private readonly AdmissionService $admissions) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::in(array_column(AdmissionStatus::cases(), 'value'))],
            'academic_year' => ['nullable', 'string', 'regex:/^\d{4}-\d{4}$/'],
        ]);

        return AdmissionResource::collection(
            $this->admissions->list(
                $request->only('search', 'status', 'academic_year', 'level', 'sort', 'direction'),
                $request->integer('per_page', 15),
            ),
        );
    }

    public function summary(Request $request): JsonResponse
    {
        $request->validate(['academic_year' => ['nullable', 'string', 'regex:/^\d{4}-\d{4}$/']]);

        return response()->json(['data' => $this->admissions->summary($request->query('academic_year'))]);
    }

    public function store(StoreAdmissionRequest $request): JsonResponse
    {
        $application = $this->admissions->submit($request->validated());

        return (new AdmissionResource($application))->response()->setStatusCode(201);
    }

    public function show(AdmissionApplication $admission): AdmissionResource
    {
        return new AdmissionResource($this->admissions->find($admission->id));
    }

    public function update(UpdateAdmissionRequest $request, AdmissionApplication $admission): AdmissionResource
    {
        return new AdmissionResource($this->admissions->update($admission, $request->validated()));
    }

    public function destroy(AdmissionApplication $admission): Response
    {
        $this->admissions->delete($admission);

        return response()->noContent();
    }

    /** Instruit le dossier : mise en etude, admission, liste d'attente ou refus. */
    public function changeStatus(ChangeAdmissionStatusRequest $request, AdmissionApplication $admission): AdmissionResource
    {
        return new AdmissionResource(
            $this->admissions->changeStatus($admission, $request->status(), $request->validated('note'), $request->user()->id),
        );
    }

    /**
     * Inscrit le candidat admis dans une classe. Retourne aussi son matricule
     * et son mot de passe initial : c'est la seule fois ou ce mot de passe est
     * lisible en clair, a remettre au tuteur.
     */
    public function enroll(EnrollAdmissionRequest $request, AdmissionApplication $admission): JsonResponse
    {
        $result = $this->admissions->enroll($admission, $request->validated('school_class_id'));

        return response()->json([
            'data' => [
                'application' => (new AdmissionResource($result['application']))->resolve(),
                'student' => (new StudentResource($result['student']))->resolve(),
                'initial_password' => $result['initial_password'],
            ],
        ], 201);
    }
}
