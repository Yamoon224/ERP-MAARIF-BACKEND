<?php

namespace App\Domains\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Demande de lien de reinitialisation du mot de passe, par le personnel (e-mail du compte). */
class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }
}
