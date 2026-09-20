<?php

namespace App\Domains\Accounting\Http\Requests;

use App\Domains\Accounting\Enums\PaymentMethod;
use App\Domains\Accounting\Enums\PaymentPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Le montant n'est pas saisi : il est calcule a partir des mois regles. */
class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'enrollment_id' => ['required', 'uuid', Rule::exists('enrollments', 'id')],
            'period' => ['required', Rule::in(PaymentPeriod::values())],
            'method' => ['required', Rule::in(PaymentMethod::values())],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
