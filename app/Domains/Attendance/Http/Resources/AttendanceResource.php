<?php

namespace App\Domains\Attendance\Http\Resources;

use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AttendanceRecord */
class AttendanceResource extends JsonResource
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
            'date' => $this->date?->toDateString(),
            'status' => $this->status->value,
            'justified' => $this->justified,
            'reason' => $this->reason,
        ];
    }
}
