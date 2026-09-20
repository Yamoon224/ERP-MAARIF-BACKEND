<?php

namespace App\Domains\Students\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnrollStudentRequest extends FormRequest
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
