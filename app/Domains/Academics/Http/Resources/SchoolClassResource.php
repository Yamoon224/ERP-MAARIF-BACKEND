<?php

namespace App\Domains\Academics\Http\Resources;

use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SchoolClass */
class SchoolClassResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'level' => $this->level,
            'academic_year' => $this->academic_year,
            'monthly_fee' => (float) $this->monthly_fee,
            'students_count' => $this->whenCounted('students'),
            'main_teacher' => $this->whenLoaded('mainTeacher', fn () => $this->mainTeacher ? [
                'id' => $this->mainTeacher->id,
                'name' => $this->mainTeacher->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
