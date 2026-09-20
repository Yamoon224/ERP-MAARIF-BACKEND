<?php

namespace App\Domains\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Connexion du parent par le matricule de son enfant (cahier des charges
 * 3.1). Le champ s'appelle `matricule`, jamais `login` ou `identifier` : le
 * formulaire doit annoncer sans ambiguite ce qui est attendu.
 */
class ParentLoginRequest extends FormRequest
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
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
