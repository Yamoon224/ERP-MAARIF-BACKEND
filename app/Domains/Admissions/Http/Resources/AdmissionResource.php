<?php

namespace App\Domains\Admissions\Http\Resources;

use App\Models\AdmissionApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AdmissionApplication */
class AdmissionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'academic_year' => $this->academic_year,
            'level' => $this->level,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->fullName(),
            'gender' => $this->gender,
            'birth_date' => $this->birth_date?->toDateString(),
            'previous_school' => $this->previous_school,
            'guardian_name' => $this->guardian_name,
            'guardian_phone' => $this->guardian_phone,
            'guardian_email' => $this->guardian_email,
            'address' => $this->address,
            'notes' => $this->notes,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'submitted_on' => $this->submitted_on?->toDateString(),
            'decision_note' => $this->decision_note,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'notified_at' => $this->notified_at?->toIso8601String(),
            'decided_by' => $this->whenLoaded('decider', fn () => $this->decider ? ['id' => $this->decider->id, 'name' => $this->decider->name] : null),
            'student' => $this->whenLoaded('student', fn () => $this->student ? ['id' => $this->student->id, 'matricule' => $this->student->matricule] : null),
            'enrolled_at' => $this->enrolled_at?->toIso8601String(),
        ];
    }
}
