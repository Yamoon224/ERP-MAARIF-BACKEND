<?php

namespace Tests\Feature\Users;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    #[Test]
    public function un_administrateur_reinitialise_le_mot_de_passe_d_un_compte_et_ferme_ses_sessions(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        $teacher->createToken('telephone');
        $teacher->createToken('ordinateur');

        $this->actingAs($admin)->postJson("/api/users/{$teacher->id}/reset-password", [
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertNoContent();

        $this->assertTrue(Hash::check('nouveau-mot-de-passe', $teacher->refresh()->password));
        $this->assertSame(0, $teacher->tokens()->count());
    }

    #[Test]
    public function reinitialiser_son_propre_mot_de_passe_garde_la_session_courante(): void
    {
        $admin = $this->userWithRole('admin');
        $admin->createToken('autre-appareil');
        $current = $admin->createToken('appareil-courant')->plainTextToken;

        $this->withToken($current)->postJson("/api/users/{$admin->id}/reset-password", [
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertNoContent();

        $this->assertSame(['appareil-courant'], $admin->tokens()->pluck('name')->all());
    }

    #[Test]
    public function la_reinitialisation_exige_un_mot_de_passe_solide_et_confirme(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');

        $this->actingAs($admin)->postJson("/api/users/{$teacher->id}/reset-password", [
            'password' => 'court',
            'password_confirmation' => 'court',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->actingAs($admin)->postJson("/api/users/{$teacher->id}/reset-password", [
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'autre-chose-du-tout',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    #[Test]
    public function seul_qui_gere_les_comptes_reinitialise_un_mot_de_passe(): void
    {
        $teacher = $this->userWithRole('teacher');
        $other = $this->userWithRole('accountant');

        $this->actingAs($teacher)->postJson("/api/users/{$other->id}/reset-password", [
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertForbidden();
    }
}
