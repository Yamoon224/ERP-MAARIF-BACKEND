<?php

namespace App\Domains\Discipline\Http\Resources;

use App\Models\Summon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Summon */
class SummonResource extends JsonResource
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
            'reason' => $this->reason,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'location' => $this->location,
            'status' => $this->status->value,
            'notified_at' => $this->notified_at?->toIso8601String(),
        ];
    }
}
