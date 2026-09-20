<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function le_personnel_modifie_son_profil(): void
    {
        $teacher = $this->userWithRole('teacher');

        $this->actingAs($teacher)->putJson('/api/me', [
            'name' => 'Mariam Diallo-Camara',
            'email' => 'nouveau@maarif.test',
            'phone' => '+224611111111',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Mariam Diallo-Camara')
            ->assertJsonPath('data.email', 'nouveau@maarif.test')
            ->assertJsonPath('data.roles', ['teacher']);

        $this->assertDatabaseHas('users', ['id' => $teacher->id, 'email' => 'nouveau@maarif.test']);
    }

    #[Test]
    public function l_email_d_un_autre_compte_est_refuse_mais_le_sien_est_accepte(): void
    {
        $teacher = $this->userWithRole('teacher');
        $other = User::factory()->create();

        $this->actingAs($teacher)->putJson('/api/me', ['name' => 'Moi', 'email' => $other->email])
            ->assertStatus(422)->assertJsonValidationErrors('email');

        $this->actingAs($teacher)->putJson('/api/me', ['name' => 'Moi', 'email' => $teacher->email])->assertOk();
    }

    #[Test]
    public function le_profil_ne_permet_pas_de_s_attribuer_un_role(): void
    {
        $teacher = $this->userWithRole('teacher');

        $this->actingAs($teacher)->putJson('/api/me', [
            'name' => 'Moi',
            'email' => $teacher->email,
            'roles' => ['admin'],
            'is_active' => false,
        ])->assertOk();

        $this->assertTrue($teacher->refresh()->hasRole('teacher'));
        $this->assertFalse($teacher->hasRole('admin'));
        $this->assertTrue($teacher->is_active);
    }

    #[Test]
    public function le_mot_de_passe_se_change_avec_l_ancien(): void
    {
        $teacher = $this->userWithRole('teacher');
        $teacher->update(['password' => 'ancien-mot-de-passe']);

        $this->actingAs($teacher)->putJson('/api/me/password', [
            'current_password' => 'ancien-mot-de-passe',
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertNoContent();

        $this->assertTrue(Hash::check('nouveau-mot-de-passe', $teacher->refresh()->password));
    }

    #[Test]
    public function un_ancien_mot_de_passe_faux_est_refuse(): void
    {
        $teacher = $this->userWithRole('teacher');
        $teacher->update(['password' => 'ancien-mot-de-passe']);

        $this->actingAs($teacher)->putJson('/api/me/password', [
            'current_password' => 'mauvais',
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('ancien-mot-de-passe', $teacher->refresh()->password));
    }

    #[Test]
    public function le_nouveau_mot_de_passe_doit_etre_confirme_long_et_different(): void
    {
        $teacher = $this->userWithRole('teacher');
        $teacher->update(['password' => 'ancien-mot-de-passe']);

        $this->actingAs($teacher)->putJson('/api/me/password', [
            'current_password' => 'ancien-mot-de-passe',
            'password' => 'court',
            'password_confirmation' => 'court',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->actingAs($teacher)->putJson('/api/me/password', [
            'current_password' => 'ancien-mot-de-passe',
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'autre-chose-du-tout',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->actingAs($teacher)->putJson('/api/me/password', [
            'current_password' => 'ancien-mot-de-passe',
            'password' => 'ancien-mot-de-passe',
            'password_confirmation' => 'ancien-mot-de-passe',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    #[Test]
    public function changer_de_mot_de_passe_ferme_les_autres_sessions_mais_garde_la_courante(): void
    {
        $teacher = $this->userWithRole('teacher');
        $teacher->update(['password' => 'ancien-mot-de-passe']);
        $otherSession = $teacher->createToken('autre-appareil')->plainTextToken;
        $currentSession = $teacher->createToken('appareil-courant')->plainTextToken;

        $this->withToken($currentSession)->putJson('/api/me/password', [
            'current_password' => 'ancien-mot-de-passe',
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertNoContent();

        $this->assertSame(1, $teacher->tokens()->count());
        $this->assertSame('appareil-courant', $teacher->tokens()->first()->name);
        $this->assertNotEmpty($otherSession);
    }

    #[Test]
    public function le_parent_change_le_mot_de_passe_du_portail(): void
    {
        $student = $this->studentWithPassword(); // mot de passe "password"

        $this->actingAs($student)->putJson('/api/parent/me/password', [
            'current_password' => 'password',
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertNoContent();

        $this->assertTrue(Hash::check('nouveau-mot-de-passe', $student->refresh()->password));

        $this->actingAs($student)->putJson('/api/parent/me/password', [
            'current_password' => 'password',
            'password' => 'encore-un-autre',
            'password_confirmation' => 'encore-un-autre',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');
    }

    #[Test]
    public function un_parent_ne_peut_pas_modifier_un_profil_du_personnel(): void
    {
        $this->actingAs($this->studentWithPassword())->putJson('/api/me', ['name' => 'X', 'email' => 'x@x.test'])->assertForbidden();
    }
}
