<?php

namespace App\Domains\Students\Http\Controllers;

use App\Domains\Students\Http\Requests\StoreStudentRequest;
use App\Domains\Students\Http\Requests\UpdateStudentRequest;
use App\Domains\Students\Http\Resources\StudentResource;
use App\Domains\Students\Services\StudentService;
use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Gestion des eleves (cahier des charges 3.4), reservee au personnel. */
class StudentController extends Controller
{
    public function __construct(private readonly StudentService $students) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return StudentResource::collection(
            $this->students->list(
                $request->only('search', 'school_class_id', 'is_active', 'sort', 'direction'),
                $request->integer('per_page', 15),
            ),
        );
    }

    /**
     * Inscrit un eleve et retourne le matricule et le mot de passe initial du
     * portail parent : c'est la seule fois ou ce mot de passe est lisible en
     * clair, a remettre au tuteur.
     */
    public function store(StoreStudentRequest $request): JsonResponse
    {
        $result = $this->students->enroll($request->validated());

        return response()->json([
            'data' => [
                ...(new StudentResource($result['student']))->resolve(),
                'initial_password' => $result['initial_password'],
            ],
        ], 201);
    }

    public function show(Student $student): StudentResource
    {
        return new StudentResource($this->students->find($student->id));
    }

    public function update(UpdateStudentRequest $request, Student $student): StudentResource
    {
        return new StudentResource($this->students->update($student, $request->validated()));
    }

    public function destroy(Student $student): Response
    {
        $this->students->delete($student);

        return response()->noContent();
    }

    /** Reinitialise le mot de passe du portail parent, ex. apres une perte. */
    public function resetPassword(Student $student): JsonResponse
    {
        $newPassword = $this->students->resetPassword($student);

        return response()->json(['data' => ['initial_password' => $newPassword]]);
    }
}
