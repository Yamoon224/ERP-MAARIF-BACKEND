<?php

namespace App\Domains\Grades\Http\Resources;

use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Grade */
class GradeResource extends JsonResource
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
            'subject' => $this->whenLoaded('subject', fn () => [
                'id' => $this->subject->id,
                'name' => $this->subject->name,
                'code' => $this->subject->code,
            ]),
            'term' => $this->whenLoaded('term', fn () => [
                'id' => $this->term->id,
                'name' => $this->term->name,
            ]),
            'type' => $this->type->value,
            'label' => $this->label,
            'value' => (float) $this->value,
            'max_value' => (float) $this->max_value,
            'normalized_on_20' => $this->normalizedOn20(),
            'recorded_at' => $this->recorded_at?->toDateString(),
            'comment' => $this->comment,
        ];
    }
}
