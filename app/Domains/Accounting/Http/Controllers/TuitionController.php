<?php

namespace App\Domains\Accounting\Http\Controllers;

use App\Domains\Accounting\Enums\PaymentPeriod;
use App\Domains\Accounting\Services\PaymentService;
use App\Domains\Accounting\Services\TuitionService;
use App\Domains\Students\Http\Resources\EnrollmentResource;
use App\Domains\Students\Services\EnrollmentService;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Releve de scolarite d'une inscription (echeances mensuelles et etat de paiement). */
class TuitionController extends Controller
{
    public function __construct(
        private readonly TuitionService $tuition,
        private readonly PaymentService $payments,
        private readonly EnrollmentService $enrollments,
    ) {}

    public function show(Enrollment $enrollment): JsonResponse
    {
        return response()->json(['data' => $this->statement($enrollment)]);
    }

    /** Quels mois et quel montant pour une formule de paiement, sans rien enregistrer. */
    public function preview(Request $request, Enrollment $enrollment): JsonResponse
    {
        $validated = $request->validate(['period' => ['required', Rule::in(PaymentPeriod::values())]]);

        $enrollment->loadMissing('schoolClass:id,name,level,monthly_fee');

        return response()->json([
            'data' => $this->payments->preview($enrollment, PaymentPeriod::from($validated['period'])),
        ]);
    }

    /** Releves de son enfant, un par annee scolaire, pour le portail parent. */
    public function mine(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        return response()->json([
            'data' => $this->enrollments->history($student)
                ->map(fn (Enrollment $enrollment) => $this->statement($enrollment))
                ->values(),
        ]);
    }

    /** @return array<string, mixed> */
    private function statement(Enrollment $enrollment): array
    {
        $enrollment->loadMissing(['student:id,first_name,last_name,matricule', 'schoolClass:id,name,level,monthly_fee']);

        return [
            'enrollment' => (new EnrollmentResource($enrollment))->resolve(),
            ...$this->tuition->statement($enrollment),
        ];
    }
}
