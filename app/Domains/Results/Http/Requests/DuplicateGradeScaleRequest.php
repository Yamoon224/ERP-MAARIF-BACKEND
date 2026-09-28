<?php

namespace App\Domains\Results\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DuplicateGradeScaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'target_class_ids' => ['required', 'array', 'min:1'],
            'target_class_ids.*' => ['uuid', Rule::exists('school_classes', 'id')],
        ];
    }
}
