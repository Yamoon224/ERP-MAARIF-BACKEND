<?php

namespace App\Domains\Accounting\Http\Resources;

use App\Models\MobileMoneyTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MobileMoneyTransaction */
class MobileMoneyTransactionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'operator' => $this->operator->value,
            'operator_label' => $this->operator->label(),
            'phone' => $this->phone,
            'period_type' => $this->period_type->value,
            'period_label' => $this->period_type->label(),
            'months' => $this->months,
            'months_count' => count($this->months),
            'amount' => (float) $this->amount,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'failure_reason' => $this->failure_reason,
            'receipt_number' => $this->whenLoaded('payment', fn () => $this->payment?->receipt_number),
            'student' => $this->whenLoaded('enrollment', fn () => $this->enrollment->relationLoaded('student') && $this->enrollment->student ? [
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
            'expires_at' => $this->expires_at->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
