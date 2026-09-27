<?php

namespace App\Domains\Attendance\Http\Resources;

use App\Models\AttendanceGateSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AttendanceGateSetting */
class AttendanceGateSettingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'radius_meters' => $this->radius_meters,
            'is_enabled' => $this->is_enabled,
            'gate_token' => $this->gate_token,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
