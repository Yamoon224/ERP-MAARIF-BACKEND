<?php

namespace App\Domains\Accounting\Http\Controllers;

use App\Domains\Accounting\Enums\PaymentPeriod;
use App\Domains\Accounting\Http\Requests\CancelPaymentRequest;
use App\Domains\Accounting\Http\Requests\StorePaymentRequest;
use App\Domains\Accounting\Http\Resources\PaymentResource;
use App\Domains\Accounting\Services\PaymentService;
use App\Domains\Shared\Support\Period;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Encaissement de la scolarite et historique des paiements. */
class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            ...Period::rules(),
            'student_id' => ['nullable', 'uuid'],
            'school_class_id' => ['nullable', 'uuid'],
            'status' => ['nullable', 'in:valid,cancelled'],
        ]);

        return PaymentResource::collection($this->payments->list(
            $request->only('student_id', 'enrollment_id', 'school_class_id', 'period_type', 'method', 'status', 'search', 'academic_year', 'term_id', 'month', 'sort', 'direction'),
            $request->integer('per_page', 15),
        ));
    }

    public function show(Payment $payment): PaymentResource
    {
        return new PaymentResource($this->payments->find($payment->id));
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $payment = $this->payments->register(
            Enrollment::query()->with('schoolClass:id,name,level,monthly_fee')->findOrFail($validated['enrollment_id']),
            PaymentPeriod::from($validated['period']),
            $validated,
            $request->user()->id,
        );

        return (new PaymentResource($payment))->response()->setStatusCode(201);
    }

    public function cancel(CancelPaymentRequest $request, Payment $payment): PaymentResource
    {
        return new PaymentResource(
            $this->payments->cancel($payment, $request->validated('reason'), $request->user()->id),
        );
    }

    /** Paiements de scolarite de son enfant, pour le portail parent. */
    public function mine(Request $request): AnonymousResourceCollection
    {
        $request->validate(Period::rules());

        /** @var Student $student */
        $student = $request->user();

        return PaymentResource::collection($this->payments->list(
            ['student_id' => $student->id, 'status' => 'valid', ...$request->only('academic_year', 'term_id', 'month')],
            $request->integer('per_page', 15),
        ));
    }
}
