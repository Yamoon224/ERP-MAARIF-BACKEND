<?php

namespace App\Domains\Attendance\Repositories;

use App\Domains\Attendance\Contracts\AttendanceGateSettingRepositoryContract;
use App\Models\AttendanceGateSetting;
use Illuminate\Support\Str;

final class EloquentAttendanceGateSettingRepository implements AttendanceGateSettingRepositoryContract
{
    public function current(): AttendanceGateSetting
    {
        return AttendanceGateSetting::query()->first() ?? AttendanceGateSetting::create([
            'radius_meters' => 100,
            'gate_token' => (string) Str::uuid(),
            'is_enabled' => false,
        ]);
    }

    public function update(AttendanceGateSetting $setting, array $attributes): AttendanceGateSetting
    {
        $setting->update($attributes);

        return $setting->refresh();
    }

    public function regenerateToken(AttendanceGateSetting $setting): AttendanceGateSetting
    {
        $setting->update(['gate_token' => (string) Str::uuid()]);

        return $setting->refresh();
    }
}
