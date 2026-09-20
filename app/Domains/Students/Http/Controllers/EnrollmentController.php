<?php

namespace App\Domains\Students\Http\Controllers;

use App\Domains\Students\Http\Requests\EnrollStudentRequest;
use App\Domains\Students\Http\Resources\EnrollmentResource;
use App\Domains\Students\Services\EnrollmentService;
use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Inscriptions annuelles d'un eleve (une par annee scolaire). */
class EnrollmentController extends Controller
{
    public function __construct(private readonly EnrollmentService $enrollments) {}

    public function index(Student $student): AnonymousResourceCollection
    {
        return EnrollmentResource::collection($this->enrollments->history($student));
    }

    /** Reinscrit l'eleve dans une classe (nouvelle annee, ou changement de classe). */
    public function store(EnrollStudentRequest $request, Student $student): JsonResponse
    {
        $enrollment = $this->enrollments->enroll($student, $request->validated('school_class_id'));

        return (new EnrollmentResource($enrollment))->response()->setStatusCode(201);
    }
}
