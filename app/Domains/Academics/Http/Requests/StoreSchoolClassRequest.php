<?php

namespace App\Domains\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchoolClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'level' => ['required', 'string', 'max:100'],
            'academic_year' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'main_teacher_id' => ['nullable', 'uuid', Rule::exists('users', 'id')],
        ];
    }
}
