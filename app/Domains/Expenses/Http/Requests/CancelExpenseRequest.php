<?php

namespace App\Domains\Expenses\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
