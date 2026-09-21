<?php

namespace Tests\Feature\Auth;

use App\Domains\Notifications\Contracts\NotificationSenderContract;
use App\Domains\Notifications\DTOs\NotificationMessage;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Senders\ArrayNotificationSender;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** "Mot de passe oublie" : lien a usage unique envoye au titulaire (personnel) ou au tuteur (parent). */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<NotificationMessage> */
    private function sent(): array
    {
        /** @var ArrayNotificationSender $sender */
        $sender = $this->app->make(NotificationSenderContract::class);

        return $sender->sent();
    }

    private function tokenFrom(NotificationMessage $message): string
    {
        preg_match('/token=([^&\s]+)/', $message->body, $matches);

        return urldecode($matches[1]);
    }

    // --- Personnel -----------------------------------------------------------

    #[Test]
    public function le_personnel_recoit_un_lien_de_reinitialisation_par_e_mail(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

        $messages = $this->sent();
        $this->assertCount(1, $messages);
        $this->assertSame(NotificationChannel::Email, $messages[0]->channel);
        $this->assertSame($user->email, $messages[0]->recipient);
        $this->assertStringContainsString(config('app.frontend_url').'/reset-password?token=', $messages[0]->body);
        $this->assertStringContainsString(urlencode($user->email), $messages[0]->body);
    }

    /** Meme reponse que le compte existe ou non : pas d'oracle d'enumeration de comptes. */
    #[Test]
    public function une_adresse_inconnue_ou_un_compte_desactive_recoit_la_meme_reponse_sans_message(): void
    {
        $inactive = User::factory()->inactive()->create();
        $known = User::factory()->create();

        $unknown = $this->postJson('/api/forgot-password', ['email' => 'inconnu@example.test']);
        $disabled = $this->postJson('/api/forgot-password', ['email' => $inactive->email]);
        $existing = $this->postJson('/api/forgot-password', ['email' => $known->email]);

        $this->assertSame($existing->json(), $unknown->json());
        $this->assertSame($existing->json(), $disabled->json());
        $this->assertCount(1, $this->sent());
    }

    #[Test]
    public function le_lien_change_le_mot_de_passe_ferme_les_sessions_et_ne_sert_qu_une_fois(): void
    {
        $user = User::factory()->create();
        $user->createToken('poste');

        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
        $token = $this->tokenFrom($this->sent()[0]);
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'nouveau-secret', 'password_confirmation' => 'nouveau-secret'];

        $this->postJson('/api/reset-password', $payload)->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'nouveau-secret'])->assertOk();
        $this->postJson('/api/reset-password', $payload)->assertStatus(422);
    }

    #[Test]
    public function un_jeton_invalide_ne_change_pas_le_mot_de_passe(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => 'jeton-invente',
            'password' => 'nouveau-secret',
            'password_confirmation' => 'nouveau-secret',
        ])->assertStatus(422)->assertJsonPath('error_code', 'validation_failed');

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
    }

    #[Test]
    public function le_nouveau_mot_de_passe_doit_etre_confirme_et_assez_long(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/forgot-password', ['email' => $user->email]);
        $token = $this->tokenFrom($this->sent()[0]);

        $this->postJson('/api/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'court', 'password_confirmation' => 'court'])
            ->assertStatus(422)->assertJsonValidationErrors('password');
        $this->postJson('/api/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'nouveau-secret', 'password_confirmation' => 'autre-chose'])
            ->assertStatus(422)->assertJsonValidationErrors('password');
    }

    // --- Parents -------------------------------------------------------------

    #[Test]
    public function le_tuteur_recoit_le_lien_par_e_mail_a_l_adresse_du_dossier(): void
    {
        $student = Student::factory()->create(['guardian_email' => 'tuteur@example.test']);

        $this->postJson('/api/parent/forgot-password', ['matricule' => $student->matricule])->assertOk();

        $messages = $this->sent();
        $this->assertCount(1, $messages);
        $this->assertSame(NotificationChannel::Email, $messages[0]->channel);
        $this->assertSame('tuteur@example.test', $messages[0]->recipient);
        $this->assertStringContainsString(config('app.frontend_url').'/portal/reset-password?token=', $messages[0]->body);
        $this->assertStringContainsString("matricule={$student->matricule}", $messages[0]->body);
    }

    #[Test]
    public function sans_e_mail_le_lien_part_par_sms_et_n_est_pas_consigne_au_journal(): void
    {
        $student = Student::factory()->create(['guardian_email' => null]);

        $this->postJson('/api/parent/forgot-password', ['matricule' => $student->matricule])->assertOk();

        $messages = $this->sent();
        $this->assertCount(1, $messages);
        $this->assertSame(NotificationChannel::Sms, $messages[0]->channel);
        $this->assertSame($student->guardian_phone, $messages[0]->recipient);
        // Le lien est un secret : le journal des notifications est lisible par le personnel.
        $this->assertDatabaseCount('notification_logs', 0);
    }

    #[Test]
    public function un_matricule_inconnu_ou_un_dossier_desactive_recoit_la_meme_reponse_sans_message(): void
    {
        $inactive = Student::factory()->inactive()->create();
        $known = Student::factory()->create();

        $unknown = $this->postJson('/api/parent/forgot-password', ['matricule' => 'MAA-9999-000000']);
        $disabled = $this->postJson('/api/parent/forgot-password', ['matricule' => $inactive->matricule]);
        $existing = $this->postJson('/api/parent/forgot-password', ['matricule' => $known->matricule]);

        $this->assertSame($existing->json(), $unknown->json());
        $this->assertSame($existing->json(), $disabled->json());
        $this->assertCount(1, $this->sent());
    }

    #[Test]
    public function la_demande_seule_ne_change_pas_le_mot_de_passe_du_portail(): void
    {
        $student = $this->studentWithPassword();

        $this->postJson('/api/parent/forgot-password', ['matricule' => $student->matricule])->assertOk();

        $this->postJson('/api/parent/login', ['matricule' => $student->matricule, 'password' => 'password'])->assertOk();
    }

    #[Test]
    public function le_lien_change_le_mot_de_passe_du_portail_et_ferme_les_sessions(): void
    {
        $student = $this->studentWithPassword();
        $student->createToken('portail');

        $this->postJson('/api/parent/forgot-password', ['matricule' => $student->matricule])->assertOk();
        $token = $this->tokenFrom($this->sent()[0]);

        $this->postJson('/api/parent/reset-password', [
            'matricule' => $student->matricule,
            'token' => $token,
            'password' => 'nouveau-secret',
            'password_confirmation' => 'nouveau-secret',
        ])->assertNoContent();

        $this->assertSame(0, $student->tokens()->count());
        $this->postJson('/api/parent/login', ['matricule' => $student->matricule, 'password' => 'password'])->assertStatus(422);
        $this->postJson('/api/parent/login', ['matricule' => $student->matricule, 'password' => 'nouveau-secret'])->assertOk();
    }

    /** Le jeton d'un eleve ne vaut rien pour un autre, meme dans une fratrie qui partage l'e-mail du tuteur. */
    #[Test]
    public function le_jeton_d_un_eleve_ne_reinitialise_pas_le_mot_de_passe_d_un_autre(): void
    {
        $first = Student::factory()->create(['guardian_email' => 'famille@example.test']);
        $second = Student::factory()->create(['guardian_email' => 'famille@example.test']);

        $this->postJson('/api/parent/forgot-password', ['matricule' => $first->matricule])->assertOk();
        $token = $this->tokenFrom($this->sent()[0]);

        $this->postJson('/api/parent/reset-password', [
            'matricule' => $second->matricule,
            'token' => $token,
            'password' => 'nouveau-secret',
            'password_confirmation' => 'nouveau-secret',
        ])->assertStatus(422);

        $this->postJson('/api/parent/login', ['matricule' => $second->matricule, 'password' => 'password'])->assertOk();
    }
}
