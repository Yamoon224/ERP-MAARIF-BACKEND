<?php

namespace App\Domains\Attendance\Http\Requests;

use App\Domains\Attendance\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::in(AttendanceStatus::values())],
            'justified' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
