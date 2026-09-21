<?php

namespace App\Domains\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Demande de lien de reinitialisation du mot de passe du portail, par le matricule de l'eleve. */
class ParentForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'matricule' => ['required', 'string'],
        ];
    }
}
