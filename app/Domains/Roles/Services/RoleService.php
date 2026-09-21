<?php

namespace App\Domains\Roles\Services;

use App\Domains\Roles\Contracts\RoleRepositoryContract;
use App\Domains\Roles\Exceptions\RoleException;
use App\Domains\Roles\Support\PermissionCatalog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Administration des roles et de leurs permissions (cahier des charges 3.1 :
 * « gestion des droits d'acces »).
 *
 * Deux garde-fous evitent qu'une fausse manoeuvre n'ampute la plateforme :
 * les roles systeme ne se renomment ni ne se suppriment (le code s'appuie sur
 * leur nom), et l'administrateur garde toutes les permissions. Un role encore
 * porte par un compte ne se supprime pas non plus : ce compte perdrait ses
 * droits sans que personne l'ait decide.
 */
final class RoleService
{
    public function __construct(private readonly RoleRepositoryContract $roles) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Role>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->roles->paginate($filters, $perPage);
    }

    public function find(string $id): Role
    {
        return $this->roles->findOrFail($id);
    }

    /** @return Collection<int, Permission> */
    public function permissions(): Collection
    {
        return Permission::query()->orderBy('name')->get();
    }

    /** @param  list<string>  $permissions  noms de permissions */
    public function create(string $name, array $permissions = []): Role
    {
        return DB::transaction(function () use ($name, $permissions): Role {
            $role = $this->roles->create(['name' => $name]);
            $role->syncPermissions($permissions);

            return $this->roles->findOrFail($role->id);
        });
    }

    /** @throws RoleException */
    public function rename(Role $role, string $name): Role
    {
        if ($name !== $role->name && PermissionCatalog::isSystemRole($role->name)) {
            throw RoleException::protected(PermissionCatalog::roleLabel($role->name));
        }

        $this->roles->update($role, ['name' => $name]);

        return $this->roles->findOrFail($role->id);
    }

    /** @throws RoleException */
    public function delete(Role $role): void
    {
        if (PermissionCatalog::isSystemRole($role->name)) {
            throw RoleException::protected(PermissionCatalog::roleLabel($role->name));
        }

        $users = $this->roles->countUsers($role);

        if ($users > 0) {
            throw RoleException::inUse($users);
        }

        $this->roles->delete($role);
    }

    /**
     * Remplace l'ensemble des permissions du role.
     *
     * @param  list<string>  $permissions
     *
     * @throws RoleException
     */
    public function syncPermissions(Role $role, array $permissions): Role
    {
        $this->assertEditable($role);
        $role->syncPermissions($permissions);

        return $this->roles->findOrFail($role->id);
    }

    /**
     * Ajoute des permissions au role, sans toucher a celles qu'il a deja.
     *
     * @param  list<string>  $permissions
     *
     * @throws RoleException
     */
    public function grantPermissions(Role $role, array $permissions): Role
    {
        $this->assertEditable($role);
        $role->givePermissionTo($permissions);

        return $this->roles->findOrFail($role->id);
    }

    /** @throws RoleException */
    public function revokePermission(Role $role, Permission $permission): Role
    {
        $this->assertEditable($role);
        $role->revokePermissionTo($permission);

        return $this->roles->findOrFail($role->id);
    }

    /** @throws RoleException */
    private function assertEditable(Role $role): void
    {
        if ($role->name === 'admin') {
            throw RoleException::adminLocked();
        }
    }
}
