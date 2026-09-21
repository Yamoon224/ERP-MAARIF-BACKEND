<?php

namespace App\Domains\Academics\Http\Requests;

use App\Domains\Academics\Rules\IsTeacher;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'level' => ['sometimes', 'required', 'string', 'max:100'],
            'academic_year' => ['sometimes', 'required', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'monthly_fee' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'main_teacher_id' => ['nullable', 'uuid', new IsTeacher],
        ];
    }
}
