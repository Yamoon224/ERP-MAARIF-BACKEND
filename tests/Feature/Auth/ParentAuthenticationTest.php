<?php

namespace Tests\Feature\Auth;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ParentAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function un_parent_se_connecte_avec_le_matricule_et_le_mot_de_passe_de_son_enfant(): void
    {
        $student = $this->studentWithPassword();

        $response = $this->postJson('/api/parent/login', [
            'matricule' => $student->matricule,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['token', 'student' => ['id', 'matricule', 'first_name']]])
            ->assertJsonPath('data.student.matricule', $student->matricule);

        $this->assertNotNull($student->refresh()->last_login_at);
    }

    #[Test]
    public function un_matricule_inconnu_renvoie_le_meme_message_qu_un_mauvais_mot_de_passe(): void
    {
        $student = $this->studentWithPassword();

        $wrongPassword = $this->postJson('/api/parent/login', ['matricule' => $student->matricule, 'password' => 'faux']);
        $unknownMatricule = $this->postJson('/api/parent/login', ['matricule' => 'MAA-9999-000000', 'password' => 'faux']);

        $wrongPassword->assertStatus(422)->assertJsonPath('error_code', 'validation_failed');
        $this->assertSame($wrongPassword->json('errors.matricule'), $unknownMatricule->json('errors.matricule'));
    }

    #[Test]
    public function un_dossier_desactive_ne_peut_pas_se_connecter(): void
    {
        $student = Student::factory()->inactive()->create();

        $this->postJson('/api/parent/login', ['matricule' => $student->matricule, 'password' => 'password'])
            ->assertStatus(422);
    }

    /** Un jeton du personnel ne donne pas acces au portail parent. */
    #[Test]
    public function un_jeton_du_personnel_est_refuse_sur_les_routes_parent(): void
    {
        $admin = $this->userWithRole('admin');
        $token = $admin->createToken('poste')->plainTextToken;

        $this->withToken($token)->getJson('/api/parent/me')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');
    }

    #[Test]
    public function le_parent_ne_voit_que_les_informations_de_son_propre_enfant(): void
    {
        $student = $this->studentWithPassword();
        $token = $student->createToken('portail')->plainTextToken;

        $this->withToken($token)->getJson('/api/parent/me')
            ->assertOk()
            ->assertJsonPath('data.id', $student->id)
            ->assertJsonPath('data.matricule', $student->matricule);
    }
}
