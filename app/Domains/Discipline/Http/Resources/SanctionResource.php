<?php

namespace App\Domains\Discipline\Http\Resources;

use App\Models\Sanction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sanction */
class SanctionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'name' => $this->student->fullName(),
                'matricule' => $this->student->matricule,
            ]),
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'reason' => $this->reason,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'notified_at' => $this->notified_at?->toIso8601String(),
        ];
    }
}
