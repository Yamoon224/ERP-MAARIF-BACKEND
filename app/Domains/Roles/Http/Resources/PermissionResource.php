<?php

namespace App\Domains\Roles\Http\Resources;

use App\Domains\Roles\Support\PermissionCatalog;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Permission */
class PermissionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => PermissionCatalog::description($this->name),
            'group' => PermissionCatalog::group($this->name),
            'group_label' => PermissionCatalog::groupLabel($this->name),
        ];
    }
}
