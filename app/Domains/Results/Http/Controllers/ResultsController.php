<?php

namespace App\Domains\Results\Http\Controllers;

use App\Domains\Results\Http\Requests\ClassResultsRequest;
use App\Domains\Results\Http\Requests\PromoteClassRequest;
use App\Domains\Results\Http\Requests\SaveDecisionRequest;
use App\Domains\Results\Services\PromotionService;
use App\Domains\Results\Services\ResultsService;
use App\Domains\Shared\Support\Period;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Resultats par trimestre, semestre et annee : classement d'une classe,
 * resultats d'un eleve, et decisions de passage de fin d'annee.
 */
class ResultsController extends Controller
{
    public function __construct(
        private readonly ResultsService $results,
        private readonly PromotionService $promotions,
    ) {}

    /** Classement d'une classe sur une periode. */
    public function forClass(ClassResultsRequest $request): JsonResponse
    {
        $class = SchoolClass::query()->findOrFail($request->validated('school_class_id'));

        $period = $this->results->period(
            $class->academic_year,
            $request->kind(),
            $request->validated('term_id'),
            $request->filled('semester') ? (int) $request->validated('semester') : null,
        );

        return response()->json(['data' => $this->results->forClass($class, $period)]);
    }

    public function forStudent(Request $request, Student $student): JsonResponse
    {
        return $this->respondForStudent($request, $student);
    }

    /** Resultats de son enfant, pour le portail parent. */
    public function mine(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        return $this->respondForStudent($request, $student);
    }

    /** Enregistre la decision de passage d'un eleve (validation ou correction manuelle). */
    public function saveDecision(SaveDecisionRequest $request, Enrollment $enrollment): JsonResponse
    {
        $decision = $this->results->saveDecision($enrollment, $request->decision(), $request->validated('note'), $request->user()->id);

        return response()->json([
            'data' => [
                'enrollment_id' => $enrollment->id,
                'value' => $decision->decision->value,
                'label' => $decision->decision->label(),
                'note' => $decision->note,
                'average' => $decision->average === null ? null : (float) $decision->average,
                'decided_at' => $decision->decided_at->toIso8601String(),
            ],
        ]);
    }

    /** Valide d'un coup les decisions suggerees d'une classe. */
    public function validateDecisions(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $count = $this->results->validateClassDecisions($schoolClass, $request->user()->id);

        return response()->json(['data' => ['validated' => $count]]);
    }

    /**
     * Reinscrit les eleves de la classe pour l'annee suivante d'apres leurs
     * decisions enregistrees : admis dans la classe superieure, redoublants
     * dans la classe qu'ils repetent, exclus laisses de cote.
     */
    public function promote(PromoteClassRequest $request, SchoolClass $schoolClass): JsonResponse
    {
        $summary = $this->promotions->promoteClass(
            $schoolClass,
            $request->validated('admitted_class_id'),
            $request->validated('repeat_class_id'),
        );

        return response()->json(['data' => $summary]);
    }

    private function respondForStudent(Request $request, Student $student): JsonResponse
    {
        $request->validate(['academic_year' => Period::rules()['academic_year']]);

        return response()->json(['data' => $this->results->forStudent($student, $request->query('academic_year'))]);
    }
}
