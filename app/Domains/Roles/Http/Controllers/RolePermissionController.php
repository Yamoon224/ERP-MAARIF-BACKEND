<?php

namespace App\Domains\Roles\Http\Controllers;

use App\Domains\Roles\Http\Requests\RolePermissionsRequest;
use App\Domains\Roles\Http\Resources\RoleResource;
use App\Domains\Roles\Services\RoleService;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Validation\ValidationException;

/** Attribution et retrait des permissions d'un role donne. */
class RolePermissionController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    /** Remplace toutes les permissions du role par la liste envoyee (case a cocher d'une matrice). */
    public function sync(RolePermissionsRequest $request, Role $role): RoleResource
    {
        /** @var list<string> $permissions */
        $permissions = $request->input('permissions');

        return new RoleResource($this->roles->syncPermissions($role, $permissions));
    }

    /** Ajoute des permissions au role, sans retirer celles qu'il a deja. */
    public function attach(RolePermissionsRequest $request, Role $role): RoleResource
    {
        /** @var list<string> $permissions */
        $permissions = $request->input('permissions');

        if ($permissions === []) {
            throw ValidationException::withMessages([
                'permissions' => 'Indiquez au moins une permission à attribuer.',
            ]);
        }

        return new RoleResource($this->roles->grantPermissions($role, $permissions));
    }

    /** Retire une permission du role. */
    public function detach(Role $role, Permission $permission): RoleResource
    {
        return new RoleResource($this->roles->revokePermission($role, $permission));
    }
}
