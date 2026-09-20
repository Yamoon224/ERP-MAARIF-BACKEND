<?php

namespace App\Domains\Discipline\Http\Requests;

use App\Domains\Discipline\Enums\SummonStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSummonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['sometimes', 'required', 'string', 'max:255'],
            'scheduled_at' => ['sometimes', 'required', 'date'],
            'location' => ['nullable', 'string', 'max:150'],
            'status' => ['sometimes', 'required', Rule::in(array_column(SummonStatus::cases(), 'value'))],
        ];
    }
}
