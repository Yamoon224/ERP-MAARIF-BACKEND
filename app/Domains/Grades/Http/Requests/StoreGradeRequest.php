<?php

namespace App\Domains\Grades\Http\Requests;

use App\Domains\Grades\Enums\GradeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGradeRequest extends FormRequest
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
            'subject_id' => ['required', 'uuid', Rule::exists('subjects', 'id')],
            'term_id' => ['required', 'uuid', Rule::exists('terms', 'id')],
            'type' => ['required', Rule::in(GradeType::values())],
            'label' => ['nullable', 'string', 'max:100'],
            'value' => ['required', 'numeric', 'min:0'],
            'max_value' => ['required', 'numeric', 'gt:0'],
            'recorded_at' => ['required', 'date'],
            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->filled('value') && $this->filled('max_value') && (float) $this->input('value') > (float) $this->input('max_value')) {
                $validator->errors()->add('value', 'La note ne peut pas depasser le bareme.');
            }
        });
    }
}
