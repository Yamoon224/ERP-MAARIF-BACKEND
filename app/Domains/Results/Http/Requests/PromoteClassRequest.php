<?php

namespace App\Domains\Results\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromoteClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'admitted_class_id' => ['required', 'uuid', Rule::exists('school_classes', 'id')],
            'repeat_class_id' => ['nullable', 'uuid', Rule::exists('school_classes', 'id')],
        ];
    }
}
