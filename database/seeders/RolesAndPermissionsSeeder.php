<?php

namespace Database\Seeders;

use App\Domains\Roles\Support\PermissionCatalog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles et permissions de la plateforme (cahier des charges 3.1 : "gestion
 * des droits d'acces").
 *
 * Trois roles pour le personnel : l'administrateur gere les comptes, les
 * roles, la structure de l'etablissement et la discipline ; l'enseignant se
 * limite aux eleves, aux notes et aux presences ; le comptable encaisse la
 * scolarite et suit les impayes sans acces aux notes ni a la discipline. Les
 * parents ne sont pas concernes par ce systeme de roles : ils sont des
 * `Student` authentifie, jamais des `User` (voir App\Models\Student).
 *
 * Les permissions existent parce que le code les exige (voir
 * PermissionCatalog) ; leur attribution aux roles, elle, est ensuite reglable
 * par l'administrateur depuis l'ecran des roles. Ce seeder ne fait donc que
 * poser les valeurs de depart : il n'ecrase plus les droits d'un role deja
 * cree, sinon chaque deploiement annulerait le travail de l'administrateur.
 * Seul `admin` est toujours remis a jour avec l'integralite des permissions,
 * pour qu'une permission ajoutee au code lui soit acquise.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Role => permissions de depart.
     *
     * @var array<string, list<string>>
     */
    private const ROLES = [
        'admin' => ['*'],
        'teacher' => ['students.view', 'academics.view', 'grades.manage', 'attendance.manage', 'results.view'],
        'accountant' => ['students.view', 'academics.view', 'accounting.view', 'accounting.manage', 'expenses.view', 'expenses.manage'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect(PermissionCatalog::PERMISSIONS)->keys()->mapWithKeys(
            fn (string $name) => [$name => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])],
        );

        foreach (self::ROLES as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $isAdmin = $rolePermissions === ['*'];

            if (! $isAdmin && ! $role->wasRecentlyCreated) {
                continue;
            }

            $role->syncPermissions(
                $isAdmin ? $permissions->values() : $permissions->only($rolePermissions)->values(),
            );
        }
    }
}
