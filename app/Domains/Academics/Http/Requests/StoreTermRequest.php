<?php

namespace App\Domains\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTermRequest extends FormRequest
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
            'academic_year' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_current' => ['nullable', 'boolean'],
        ];
    }
}
