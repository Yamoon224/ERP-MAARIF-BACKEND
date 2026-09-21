<?php

namespace App\Domains\Roles\Http\Controllers;

use App\Domains\Roles\Http\Resources\PermissionResource;
use App\Domains\Roles\Services\RoleService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Catalogue des permissions existantes, pour construire la matrice d'attribution d'un role. */
class PermissionController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function index(): AnonymousResourceCollection
    {
        return PermissionResource::collection($this->roles->permissions());
    }
}
