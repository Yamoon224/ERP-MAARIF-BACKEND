<?php

namespace App\Domains\Accounting\Http\Requests;

use App\Domains\Accounting\Enums\MobileMoneyOperator;
use App\Domains\Accounting\Enums\PaymentPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Le montant n'est pas saisi : il est calculé à partir des mois réglés. */
class InitiateMobileMoneyPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Espaces, points et tirets ôtés : « 622 12 34 56 » devient « 622123456 ». */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone'))) {
            $this->merge(['phone' => preg_replace('/[\s.\-()]/', '', $this->input('phone'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'enrollment_id' => ['required', 'uuid'],
            'period' => ['required', Rule::in(PaymentPeriod::values())],
            'operator' => ['required', Rule::in(MobileMoneyOperator::values())],
            'phone' => ['required', 'regex:/^\+?[0-9]{8,15}$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Le numéro de téléphone doit contenir entre 8 et 15 chiffres.',
        ];
    }
}
