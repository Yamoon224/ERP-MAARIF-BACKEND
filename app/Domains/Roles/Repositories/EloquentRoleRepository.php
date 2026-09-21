<?php

namespace App\Domains\Roles\Repositories;

use App\Domains\Roles\Contracts\RoleRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentRoleRepository implements RoleRepositoryContract
{
    /** @var array<string, string> */
    private const SORTABLE = [
        'name' => 'name',
        'permissions_count' => 'permissions_count',
        'users_count' => 'users_count',
        'created_at' => 'created_at',
    ];

    /** @return LengthAwarePaginator<int, Role> */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Role::query()
            ->withCount(['permissions', 'users'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'name', 'asc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(string $id): Role
    {
        return Role::query()
            ->with('permissions:id,name')
            ->withCount(['permissions', 'users'])
            ->findOrFail($id);
    }

    public function create(array $attributes): Role
    {
        return Role::create([...$attributes, 'guard_name' => 'web']);
    }

    public function update(Role $role, array $attributes): Role
    {
        $role->update($attributes);

        return $role->refresh();
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }

    public function countUsers(Role $role): int
    {
        return $role->users()->count();
    }
}
