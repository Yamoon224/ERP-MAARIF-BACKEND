<?php

namespace App\Domains\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClassFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'monthly_fee' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ];
    }
}
