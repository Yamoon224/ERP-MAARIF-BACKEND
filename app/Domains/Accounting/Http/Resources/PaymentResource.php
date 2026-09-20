<?php

namespace App\Domains\Accounting\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'student' => $this->whenLoaded('enrollment', fn () => $this->enrollment->relationLoaded('student') ? [
                'id' => $this->enrollment->student->id,
                'name' => $this->enrollment->student->fullName(),
                'matricule' => $this->enrollment->student->matricule,
            ] : null),
            'enrollment' => $this->whenLoaded('enrollment', fn () => [
                'id' => $this->enrollment->id,
                'academic_year' => $this->enrollment->academic_year,
                'school_class' => $this->enrollment->relationLoaded('schoolClass') && $this->enrollment->schoolClass ? [
                    'id' => $this->enrollment->schoolClass->id,
                    'name' => $this->enrollment->schoolClass->name,
                ] : null,
            ]),
            'period_type' => $this->period_type->value,
            'period_label' => $this->period_type->label(),
            'months' => $this->months,
            'months_count' => count($this->months),
            'amount' => (float) $this->amount,
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'reference' => $this->reference,
            'paid_at' => $this->paid_at?->toDateString(),
            'note' => $this->note,
            'status' => $this->isCancelled() ? 'cancelled' : 'valid',
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'received_by' => $this->whenLoaded('receivedBy', fn () => $this->receivedBy ? [
                'id' => $this->receivedBy->id,
                'name' => $this->receivedBy->name,
            ] : null),
        ];
    }
}
