<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles et permissions de la plateforme (cahier des charges 3.1 : "gestion
 * des droits d'acces").
 *
 * Deux roles pour le personnel : l'administrateur gere les comptes, la
 * structure de l'etablissement et la discipline ; l'enseignant se limite aux
 * eleves, aux notes et aux presences. Les parents ne sont pas concernes par
 * ce systeme de roles : ils sont des `Student` authentifie, jamais des
 * `User` (voir App\Models\Student).
 *
 * Ce seeder est la source de verite des droits : ils ne se modifient pas en
 * production via une interface, pour qu'une matrice de permissions reste
 * lisible et versionnee.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Permission => raison d'etre.
     *
     * @var array<string, string>
     */
    private const PERMISSIONS = [
        'users.manage' => 'Creer, modifier et desactiver les comptes du personnel.',
        'academics.view' => 'Consulter les classes, matieres et trimestres.',
        'academics.manage' => 'Administrer les classes, matieres et trimestres.',
        'students.view' => 'Consulter le dossier des eleves et leurs bulletins.',
        'students.manage' => 'Inscrire, modifier et desactiver un eleve.',
        'grades.manage' => 'Saisir et modifier les notes.',
        'attendance.manage' => 'Saisir les presences et absences.',
        'discipline.manage' => 'Creer des convocations et des sanctions.',
        'notifications.view' => 'Consulter le journal des notifications envoyees aux tuteurs.',
    ];

    /**
     * Role => permissions.
     *
     * @var array<string, list<string>>
     */
    private const ROLES = [
        'admin' => ['*'],
        'teacher' => ['students.view', 'academics.view', 'grades.manage', 'attendance.manage'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect(self::PERMISSIONS)->keys()->mapWithKeys(
            fn (string $name) => [$name => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])],
        );

        foreach (self::ROLES as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            $role->syncPermissions(
                $rolePermissions === ['*'] ? $permissions->values() : $permissions->only($rolePermissions)->values(),
            );
        }
    }
}
