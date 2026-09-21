<?php

namespace App\Domains\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/** Nouveau mot de passe du portail, avec le jeton recu par le tuteur. */
class ParentResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'matricule' => ['required', 'string'],
            'password' => ['required', Password::min(8), 'confirmed'],
        ];
    }
}
