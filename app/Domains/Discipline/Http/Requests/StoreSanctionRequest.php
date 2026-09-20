<?php

namespace App\Domains\Discipline\Http\Requests;

use App\Domains\Discipline\Enums\SanctionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSanctionRequest extends FormRequest
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
            'type' => ['required', Rule::in(array_column(SanctionType::cases(), 'value'))],
            'reason' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}
