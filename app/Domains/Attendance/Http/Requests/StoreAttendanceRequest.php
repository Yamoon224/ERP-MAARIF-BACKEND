<?php

namespace App\Domains\Attendance\Http\Requests;

use App\Domains\Attendance\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'uuid', Rule::exists('students', 'id')],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::in(AttendanceStatus::values())],
            'justified' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
