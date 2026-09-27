<?php

namespace App\Domains\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'between:10,1000'],
            'is_enabled' => ['required', 'boolean'],
        ];
    }
}
