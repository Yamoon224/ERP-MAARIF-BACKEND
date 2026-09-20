<?php

namespace App\Domains\Attendance\Http\Requests;

use App\Domains\Attendance\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Appel d'une classe entiere pour une date. */
class BulkAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'school_class_id' => ['required', 'uuid', Rule::exists('school_classes', 'id')],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'records' => ['required', 'array', 'min:1', 'max:200'],
            'records.*.student_id' => ['required', 'uuid', 'distinct', Rule::exists('students', 'id')],
            'records.*.status' => ['required', Rule::in(AttendanceStatus::values())],
            'records.*.justified' => ['nullable', 'boolean'],
            'records.*.reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
