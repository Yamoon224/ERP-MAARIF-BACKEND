<?php

namespace App\Domains\Results\Http\Requests;

use App\Domains\Results\Enums\PromotionDecisionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveGradeScaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'bands' => ['present', 'array'],
            'bands.*.min_average' => ['required', 'numeric', 'between:0,20'],
            'bands.*.max_average' => ['required', 'numeric', 'between:0,20'],
            'bands.*.label' => ['required', 'string', 'max:50'],
            'bands.*.decision' => ['nullable', Rule::in(array_column(PromotionDecisionType::cases(), 'value'))],
        ];
    }
}
