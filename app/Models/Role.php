<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Role applicatif, etendu pour porter une cle primaire UUID comme le reste du
 * schema : la table pivot `model_has_roles` associe un role a un utilisateur
 * dont l'identifiant est deja un UUID.
 *
 * @property string $id
 */
class Role extends SpatieRole
{
    use HasUuids;
}
