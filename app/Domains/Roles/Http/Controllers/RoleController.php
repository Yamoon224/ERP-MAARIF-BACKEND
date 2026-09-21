<?php

namespace App\Domains\Roles\Http\Controllers;

use App\Domains\Roles\Http\Requests\RoleRequest;
use App\Domains\Roles\Http\Resources\RoleResource;
use App\Domains\Roles\Services\RoleService;
use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Administration des roles : la lecture est ouverte a qui gere les comptes
 * (il doit pouvoir choisir un role), l'ecriture est reservee a
 * `roles.manage` (voir routes/api.php).
 */
class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return RoleResource::collection(
            $this->roles->list(
                $request->only('search', 'sort', 'direction'),
                $request->integer('per_page', 15),
            ),
        );
    }

    public function store(RoleRequest $request): JsonResponse
    {
        /** @var list<string> $permissions */
        $permissions = $request->input('permissions', []);

        return (new RoleResource($this->roles->create($request->string('name')->toString(), $permissions)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Role $role): RoleResource
    {
        return new RoleResource($this->roles->find($role->id));
    }

    public function update(RoleRequest $request, Role $role): RoleResource
    {
        return new RoleResource($this->roles->rename($role, $request->string('name')->toString()));
    }

    public function destroy(Role $role): Response
    {
        $this->roles->delete($role);

        return response()->noContent();
    }
}
