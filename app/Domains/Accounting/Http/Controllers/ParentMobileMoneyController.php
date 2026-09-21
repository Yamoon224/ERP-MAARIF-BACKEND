<?php

namespace App\Domains\Accounting\Http\Controllers;

use App\Domains\Accounting\Enums\MobileMoneyOperator;
use App\Domains\Accounting\Enums\PaymentPeriod;
use App\Domains\Accounting\Http\Requests\InitiateMobileMoneyPaymentRequest;
use App\Domains\Accounting\Http\Resources\MobileMoneyTransactionResource;
use App\Domains\Accounting\Services\MobileMoneyService;
use App\Domains\Accounting\Services\PaymentService;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\MobileMoneyTransaction;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Paiement de la scolarité de son enfant par mobile money, depuis le portail
 * parent. Un parent ne voit et ne paie que les inscriptions de son enfant.
 */
class ParentMobileMoneyController extends Controller
{
    public function __construct(
        private readonly MobileMoneyService $mobileMoney,
        private readonly PaymentService $payments,
    ) {}

    /** Quels mois et quel montant pour une formule, avant de payer. */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enrollment_id' => ['required', 'uuid'],
            'period' => ['required', Rule::in(PaymentPeriod::values())],
        ]);

        $enrollment = $this->ownEnrollment($request, $validated['enrollment_id']);

        return response()->json([
            'data' => $this->payments->preview($enrollment, PaymentPeriod::from($validated['period'])),
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Student $student */
        $student = $request->user();

        return MobileMoneyTransactionResource::collection(
            $this->mobileMoney->list(['student_id' => $student->id], $request->integer('per_page', 15)),
        );
    }

    public function store(InitiateMobileMoneyPaymentRequest $request): JsonResponse
    {
        $enrollment = $this->ownEnrollment($request, $request->validated('enrollment_id'));

        $transaction = $this->mobileMoney->initiate(
            $enrollment,
            PaymentPeriod::from($request->validated('period')),
            MobileMoneyOperator::from($request->validated('operator')),
            $request->validated('phone'),
        );

        return (new MobileMoneyTransactionResource($transaction))->response()->setStatusCode(201);
    }

    /** Sert aussi de sondage : chaque appel demande à l'opérateur où en est une demande encore en attente. */
    public function show(Request $request, MobileMoneyTransaction $transaction): MobileMoneyTransactionResource
    {
        $this->ownEnrollment($request, $transaction->enrollment_id);

        $this->mobileMoney->refresh($transaction);

        return new MobileMoneyTransactionResource($this->mobileMoney->find($transaction->id));
    }

    /** Une inscription qui n'est pas celle de l'enfant du parent est traitée comme inexistante. */
    private function ownEnrollment(Request $request, string $enrollmentId): Enrollment
    {
        /** @var Student $student */
        $student = $request->user();

        return Enrollment::query()
            ->where('student_id', $student->id)
            ->with('schoolClass:id,name,level,monthly_fee')
            ->findOrFail($enrollmentId);
    }
}
