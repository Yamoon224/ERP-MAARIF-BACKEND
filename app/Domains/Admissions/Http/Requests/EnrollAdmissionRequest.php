<?php

namespace App\Domains\Admissions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnrollAdmissionRequest extends FormRequest
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
        ];
    }
}
