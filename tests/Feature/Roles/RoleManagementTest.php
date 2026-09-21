<?php

namespace Tests\Feature\Roles;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function un_administrateur_liste_les_roles_avec_leurs_effectifs(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->getJson('/api/roles')->assertOk();

        $admin = collect($response->json('data'))->firstWhere('name', 'admin');
        $this->assertTrue($admin['is_system']);
        $this->assertSame('Administrateur', $admin['label']);
        $this->assertSame(1, $admin['users_count']);
        $this->assertSame(Permission::count(), $admin['permissions_count']);
    }

    #[Test]
    public function un_enseignant_ne_peut_pas_administrer_les_roles(): void
    {
        $teacher = $this->userWithRole('teacher');

        $this->actingAs($teacher)->getJson('/api/roles')->assertForbidden();
        $this->actingAs($teacher)->postJson('/api/roles', ['name' => 'Intrus'])->assertForbidden();
        $this->actingAs($teacher)->getJson('/api/permissions')->assertForbidden();
    }

    #[Test]
    public function qui_gere_les_comptes_peut_lister_les_roles_mais_pas_les_modifier(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $role = Role::create(['name' => 'Gestionnaire de comptes', 'guard_name' => 'web']);
        $role->givePermissionTo('users.manage');
        $manager = User::factory()->create();
        $manager->assignRole($role);

        $this->actingAs($manager)->getJson('/api/roles')->assertOk();
        $this->actingAs($manager)->postJson('/api/roles', ['name' => 'Autre'])->assertForbidden();
    }

    #[Test]
    public function un_administrateur_cree_un_role_avec_ses_permissions_de_depart(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->postJson('/api/roles', [
            'name' => 'Secrétaire',
            'permissions' => ['students.view', 'students.manage'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Secrétaire')
            ->assertJsonPath('data.is_system', false)
            ->assertJsonPath('data.permissions', ['students.manage', 'students.view']);
        $this->assertDatabaseHas('roles', ['name' => 'Secrétaire', 'guard_name' => 'web']);
    }

    #[Test]
    public function le_nom_d_un_role_est_unique_et_ses_permissions_doivent_exister(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->postJson('/api/roles', ['name' => 'teacher'])
            ->assertStatus(422)->assertJsonValidationErrors('name');

        $this->actingAs($admin)->postJson('/api/roles', ['name' => 'Fantôme', 'permissions' => ['rien.du.tout']])
            ->assertStatus(422)->assertJsonValidationErrors('permissions.0');
    }

    #[Test]
    public function un_administrateur_renomme_un_role_personnalise(): void
    {
        $admin = $this->userWithRole('admin');
        $role = Role::create(['name' => 'Surveillant', 'guard_name' => 'web']);

        $this->actingAs($admin)->putJson("/api/roles/{$role->id}", ['name' => 'Surveillant général'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Surveillant général');
    }

    #[Test]
    public function un_role_systeme_ne_peut_pas_etre_renomme(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = Role::findByName('teacher', 'web');

        $this->actingAs($admin)->putJson("/api/roles/{$teacher->id}", ['name' => 'Professeur'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'role_protected');

        $this->assertDatabaseHas('roles', ['name' => 'teacher']);
    }

    #[Test]
    public function un_administrateur_supprime_un_role_personnalise_inutilise(): void
    {
        $admin = $this->userWithRole('admin');
        $role = Role::create(['name' => 'Temporaire', 'guard_name' => 'web']);

        $this->actingAs($admin)->deleteJson("/api/roles/{$role->id}")->assertNoContent();

        $this->assertDatabaseMissing('roles', ['name' => 'Temporaire']);
    }

    #[Test]
    public function un_role_systeme_ne_peut_pas_etre_supprime(): void
    {
        $admin = $this->userWithRole('admin');
        $accountant = Role::findByName('accountant', 'web');

        $this->actingAs($admin)->deleteJson("/api/roles/{$accountant->id}")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'role_protected');
    }

    #[Test]
    public function un_role_encore_attribue_ne_peut_pas_etre_supprime(): void
    {
        $admin = $this->userWithRole('admin');
        $role = Role::create(['name' => 'Bibliothécaire', 'guard_name' => 'web']);
        User::factory()->create()->assignRole($role);

        $this->actingAs($admin)->deleteJson("/api/roles/{$role->id}")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'role_in_use')
            ->assertJsonPath('context.users_count', 1);

        $this->assertDatabaseHas('roles', ['name' => 'Bibliothécaire']);
    }

    #[Test]
    public function le_catalogue_des_permissions_porte_libelle_et_groupe(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->getJson('/api/permissions')->assertOk();

        $students = collect($response->json('data'))->firstWhere('name', 'students.view');
        $this->assertSame('students', $students['group']);
        $this->assertSame('Élèves', $students['group_label']);
        $this->assertNotEmpty($students['description']);
    }

    #[Test]
    public function un_administrateur_attribue_des_permissions_a_un_role_sans_toucher_aux_autres(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = Role::findByName('teacher', 'web');

        $response = $this->actingAs($admin)->postJson("/api/roles/{$teacher->id}/permissions", [
            'permissions' => ['discipline.manage'],
        ]);

        $response->assertOk();
        $permissions = $response->json('data.permissions');
        $this->assertContains('discipline.manage', $permissions);
        $this->assertContains('grades.manage', $permissions);
    }

    #[Test]
    public function attribuer_une_liste_vide_de_permissions_est_refuse(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = Role::findByName('teacher', 'web');

        $this->actingAs($admin)->postJson("/api/roles/{$teacher->id}/permissions", ['permissions' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('permissions');
    }

    #[Test]
    public function un_administrateur_retire_une_permission_a_un_role(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = Role::findByName('teacher', 'web');
        $permission = Permission::findByName('grades.manage', 'web');

        $response = $this->actingAs($admin)->deleteJson("/api/roles/{$teacher->id}/permissions/{$permission->id}");

        $response->assertOk();
        $this->assertNotContains('grades.manage', $response->json('data.permissions'));
        $this->assertContains('students.view', $response->json('data.permissions'));
    }

    #[Test]
    public function un_administrateur_remplace_toutes_les_permissions_d_un_role(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = Role::findByName('teacher', 'web');

        $this->actingAs($admin)->putJson("/api/roles/{$teacher->id}/permissions", [
            'permissions' => ['expenses.view'],
        ])->assertOk()->assertJsonPath('data.permissions', ['expenses.view']);

        $this->actingAs($admin)->putJson("/api/roles/{$teacher->id}/permissions", ['permissions' => []])
            ->assertOk()->assertJsonPath('data.permissions', []);
    }

    #[Test]
    public function les_permissions_de_l_administrateur_sont_verrouillees(): void
    {
        $admin = $this->userWithRole('admin');
        $adminRole = Role::findByName('admin', 'web');
        $permission = Permission::findByName('users.manage', 'web');

        $this->actingAs($admin)->putJson("/api/roles/{$adminRole->id}/permissions", ['permissions' => []])
            ->assertStatus(409)->assertJsonPath('error_code', 'role_admin_locked');
        $this->actingAs($admin)->postJson("/api/roles/{$adminRole->id}/permissions", ['permissions' => ['users.manage']])
            ->assertStatus(409)->assertJsonPath('error_code', 'role_admin_locked');
        $this->actingAs($admin)->deleteJson("/api/roles/{$adminRole->id}/permissions/{$permission->id}")
            ->assertStatus(409)->assertJsonPath('error_code', 'role_admin_locked');

        $this->assertSame(Permission::count(), $adminRole->refresh()->permissions()->count());
    }

    #[Test]
    public function une_permission_attribuee_ou_retiree_prend_effet_immediatement(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        $teacherRole = Role::findByName('teacher', 'web');
        $permission = Permission::findByName('students.view', 'web');

        $this->actingAs($teacher)->getJson('/api/students')->assertOk();

        $this->actingAs($admin)->deleteJson("/api/roles/{$teacherRole->id}/permissions/{$permission->id}")->assertOk();

        $this->actingAs($teacher->refresh())->getJson('/api/students')->assertForbidden();
    }

    #[Test]
    public function un_role_cree_depuis_l_interface_s_attribue_a_un_compte(): void
    {
        $admin = $this->userWithRole('admin');
        Role::create(['name' => 'Secrétaire', 'guard_name' => 'web'])->givePermissionTo('students.view');

        $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Nouvelle Secrétaire',
            'email' => 'secretaire@maarif.test',
            'password' => 'motdepasse-solide',
            'roles' => ['Secrétaire'],
        ])->assertCreated()->assertJsonPath('data.roles', ['Secrétaire']);

        $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Inconnu',
            'email' => 'inconnu@maarif.test',
            'password' => 'motdepasse-solide',
            'roles' => ['role-qui-n-existe-pas'],
        ])->assertStatus(422)->assertJsonValidationErrors('roles.0');
    }

    #[Test]
    public function relancer_le_seeder_n_ecrase_pas_les_reglages_faits_depuis_l_interface(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Role::findByName('teacher', 'web')->syncPermissions(['expenses.view']);

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(['expenses.view'], Role::findByName('teacher', 'web')->permissions->pluck('name')->all());
    }

    #[Test]
    public function relancer_le_seeder_redonne_toutes_les_permissions_a_l_administrateur(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $adminRole = Role::findByName('admin', 'web');
        $adminRole->revokePermissionTo('users.manage');

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(Permission::count(), $adminRole->refresh()->permissions()->count());
    }
}
