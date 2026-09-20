<?php

namespace Tests\Feature\Users;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function un_administrateur_cree_un_compte_enseignant(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Nouvel Enseignant',
            'email' => 'nouveau@maarif.test',
            'password' => 'motdepasse-solide',
            'roles' => ['teacher'],
        ]);

        $response->assertCreated()->assertJsonPath('data.roles', ['teacher']);
        $this->assertDatabaseHas('users', ['email' => 'nouveau@maarif.test']);
    }

    #[Test]
    public function un_enseignant_ne_peut_pas_gerer_les_comptes(): void
    {
        $teacher = $this->userWithRole('teacher');

        $this->actingAs($teacher)->getJson('/api/users')->assertStatus(403);
    }

    #[Test]
    public function un_administrateur_ne_peut_pas_supprimer_son_propre_compte(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->deleteJson("/api/users/{$admin->id}")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'user_self_delete');
    }

    #[Test]
    public function modifier_un_utilisateur_sans_mot_de_passe_conserve_l_ancien(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        $originalHash = $teacher->password;

        $this->actingAs($admin)->putJson("/api/users/{$teacher->id}", [
            'name' => 'Nom Modifie',
        ])->assertOk()->assertJsonPath('data.name', 'Nom Modifie');

        $this->assertSame($originalHash, $teacher->refresh()->password);
    }
}
