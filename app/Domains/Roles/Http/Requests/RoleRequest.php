<?php

namespace App\Domains\Roles\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creation ou modification d'un role : son nom est unique. A la creation, les
 * permissions initiales sont facultatives (un role peut etre cree vide puis
 * complete depuis l'ecran des permissions).
 */
class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $current = $this->route('role');

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:60',
                Rule::unique('roles', 'name')
                    ->where('guard_name', 'web')
                    ->ignore($current instanceof Role ? $current->id : null),
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nom du rôle'];
    }
}
