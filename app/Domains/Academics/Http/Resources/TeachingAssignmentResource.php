<?php

namespace App\Domains\Academics\Http\Resources;

use App\Models\ClassSubjectTeacher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClassSubjectTeacher */
class TeachingAssignmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => new SubjectResource($this->subject),
            'teacher' => $this->whenLoaded('teacher', fn () => $this->teacher ? [
                'id' => $this->teacher->id,
                'name' => $this->teacher->name,
            ] : null),
            'school_class' => $this->whenLoaded('schoolClass', fn () => [
                'id' => $this->schoolClass->id,
                'name' => $this->schoolClass->name,
                'level' => $this->schoolClass->level,
                'academic_year' => $this->schoolClass->academic_year,
            ]),
        ];
    }
}
