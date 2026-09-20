<?php

namespace App\Domains\Results\Http\Requests;

use App\Domains\Results\Enums\PromotionDecisionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(array_column(PromotionDecisionType::cases(), 'value'))],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function decision(): PromotionDecisionType
    {
        return PromotionDecisionType::from($this->validated('decision'));
    }
}
