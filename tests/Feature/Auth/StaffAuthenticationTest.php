<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function un_membre_du_personnel_se_connecte_et_recoit_ses_permissions(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->postJson('/api/login', [
            'email' => $admin->email,
            'password' => 'password',
            'device_name' => 'poste-secretariat',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'roles', 'permissions']]])
            ->assertJsonPath('data.user.roles', ['admin']);

        $this->assertContains('students.manage', $response->json('data.user.permissions'));
        $this->assertNotNull($admin->refresh()->last_login_at);
    }

    /** Meme message que le compte existe ou non : pas d'oracle d'enumeration de comptes. */
    #[Test]
    public function des_identifiants_invalides_renvoient_un_message_neutre(): void
    {
        $user = User::factory()->create();

        $wrongPassword = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'faux']);
        $unknownEmail = $this->postJson('/api/login', ['email' => 'inconnu@example.test', 'password' => 'faux']);

        $wrongPassword->assertStatus(422)->assertJsonPath('error_code', 'validation_failed');
        $this->assertSame($wrongPassword->json('errors.email'), $unknownEmail->json('errors.email'));
    }

    #[Test]
    public function un_compte_desactive_ne_peut_pas_se_connecter(): void
    {
        $user = User::factory()->inactive()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(422);
    }

    #[Test]
    public function la_deconnexion_revoque_le_jeton(): void
    {
        $user = $this->userWithRole('admin');
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
    }

    #[Test]
    public function une_route_protegee_repond_401_en_json_sans_jeton(): void
    {
        $this->getJson('/api/me')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthenticated');
    }

    /** Un jeton emis pour un eleve ne donne pas acces aux routes du personnel. */
    #[Test]
    public function un_jeton_parent_est_refuse_sur_les_routes_du_personnel(): void
    {
        $student = $this->studentWithPassword();
        $token = $student->createToken('portail')->plainTextToken;

        $this->withToken($token)->getJson('/api/me')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');
    }
}
