<?php

namespace App\Domains\Roles\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Liste de permissions a attribuer a un role, ou a lui substituer : chacune doit exister. */
class RolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Une liste vide est valide pour un remplacement complet (« aucune
        // permission ») ; l'attribution, elle, exige au moins une permission
        // (voir RolePermissionController::attach).
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ];
    }
}
