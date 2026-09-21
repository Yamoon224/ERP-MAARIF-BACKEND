<?php

namespace App\Domains\Roles\Contracts;

use App\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RoleRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Role>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /** Le role avec ses permissions et le nombre de comptes qui le portent. */
    public function findOrFail(string $id): Role;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Role;

    /** @param  array<string, mixed>  $attributes */
    public function update(Role $role, array $attributes): Role;

    public function delete(Role $role): void;

    /** Nombre de comptes du personnel qui portent ce role. */
    public function countUsers(Role $role): int;
}
