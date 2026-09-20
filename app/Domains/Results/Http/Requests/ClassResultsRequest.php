<?php

namespace App\Domains\Results\Http\Requests;

use App\Domains\Results\Support\ResultPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassResultsRequest extends FormRequest
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
            'period' => ['nullable', Rule::in(ResultPeriod::kinds())],
            'term_id' => [Rule::requiredIf(fn () => $this->input('period') === ResultPeriod::TERM), 'nullable', 'uuid', Rule::exists('terms', 'id')],
            'semester' => [Rule::requiredIf(fn () => $this->input('period') === ResultPeriod::SEMESTER), 'nullable', Rule::in([1, 2])],
        ];
    }

    public function kind(): string
    {
        return $this->validated('period') ?? ResultPeriod::ANNUAL;
    }
}
