<?php

namespace App\Domains\Grades\Http\Requests;

use App\Domains\Grades\Enums\GradeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', Rule::in(GradeType::values())],
            'label' => ['nullable', 'string', 'max:100'],
            'value' => ['sometimes', 'required', 'numeric', 'min:0'],
            'max_value' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'recorded_at' => ['sometimes', 'required', 'date'],
            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }
}
