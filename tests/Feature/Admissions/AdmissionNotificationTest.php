<?php

namespace Tests\Feature\Admissions;

use App\Domains\Admissions\Enums\AdmissionStatus;
use App\Domains\Notifications\Contracts\NotificationSenderContract;
use App\Domains\Notifications\DTOs\NotificationMessage;
use App\Domains\Notifications\Senders\ArrayNotificationSender;
use App\Models\AdmissionApplication;
use App\Models\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/** Le tuteur d'un candidat est prevenu des decisions prises sur son dossier. */
class AdmissionNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function sender(): ArrayNotificationSender
    {
        /** @var ArrayNotificationSender $sender */
        $sender = $this->app->make(NotificationSenderContract::class);

        return $sender;
    }

    #[Test]
    public function l_admission_previent_le_tuteur_a_l_adresse_du_dossier_et_le_journalise(): void
    {
        $admin = $this->userWithRole('admin');
        $application = AdmissionApplication::factory()->create([
            'first_name' => 'Mariama',
            'last_name' => 'Barry',
            'guardian_email' => 'alpha@example.test',
        ]);

        $response = $this->actingAs($admin)->postJson("/api/admissions/{$application->id}/status", ['status' => 'accepted'])
            ->assertOk();

        $this->assertCount(1, $this->sender()->sent());
        $message = $this->sender()->sent()[0];
        $this->assertSame('alpha@example.test', $message->recipient);
        $this->assertStringContainsString('Mariama Barry', $message->body);
        $this->assertStringContainsString($application->reference, $message->body);
        $this->assertStringContainsString('admise', $message->body);

        $this->assertDatabaseHas('notification_logs', [
            'admission_application_id' => $application->id,
            'student_id' => null,
            'type' => 'admission',
            'channel' => 'email',
            'status' => 'sent',
        ]);
        $this->assertNotNull($response->json('data.notified_at'));
    }

    #[Test]
    public function sans_e_mail_le_message_part_par_sms_au_telephone_du_tuteur(): void
    {
        $admin = $this->userWithRole('admin');
        $application = AdmissionApplication::factory()->create(['guardian_email' => null, 'guardian_phone' => '+224620000000']);

        $this->actingAs($admin)->postJson("/api/admissions/{$application->id}/status", ['status' => 'waitlisted'])->assertOk();

        $this->assertSame('+224620000000', $this->sender()->sent()[0]->recipient);
        $this->assertStringContainsString("liste d'attente", $this->sender()->sent()[0]->body);
        $this->assertDatabaseHas('notification_logs', ['admission_application_id' => $application->id, 'channel' => 'sms']);
    }

    #[Test]
    public function un_refus_communique_son_motif(): void
    {
        $admin = $this->userWithRole('admin');
        $application = AdmissionApplication::factory()->create();

        $this->actingAs($admin)->postJson("/api/admissions/{$application->id}/status", ['status' => 'rejected', 'note' => 'Classe complete'])->assertOk();

        $this->assertStringContainsString('non retenue', $this->sender()->sent()[0]->body);
        $this->assertStringContainsString('Classe complete', $this->sender()->sent()[0]->body);
    }

    #[Test]
    public function la_mise_en_etude_est_une_etape_interne_qui_ne_notifie_personne(): void
    {
        $admin = $this->userWithRole('admin');
        $application = AdmissionApplication::factory()->create();

        $this->actingAs($admin)->postJson("/api/admissions/{$application->id}/status", ['status' => 'under_review'])
            ->assertOk()
            ->assertJsonPath('data.notified_at', null);

        $this->assertCount(0, $this->sender()->sent());
        $this->assertDatabaseCount('notification_logs', 0);
    }

    #[Test]
    public function une_decision_inchangee_ne_renvoie_pas_le_meme_message(): void
    {
        $admin = $this->userWithRole('admin');
        $application = AdmissionApplication::factory()->create();

        $this->actingAs($admin)->postJson("/api/admissions/{$application->id}/status", ['status' => 'accepted'])->assertOk();
        $this->actingAs($admin)->postJson("/api/admissions/{$application->id}/status", ['status' => 'accepted', 'note' => 'Note corrigee'])
            ->assertOk()
            ->assertJsonPath('data.decision_note', 'Note corrigee');

        $this->assertCount(1, $this->sender()->sent());

        $this->actingAs($admin)->postJson("/api/admissions/{$application->id}/status", ['status' => 'waitlisted'])->assertOk();
        $this->assertCount(2, $this->sender()->sent());
    }

    #[Test]
    public function l_inscription_confirme_le_matricule_sans_jamais_envoyer_le_mot_de_passe(): void
    {
        $admin = $this->userWithRole('admin');
        $class = SchoolClass::factory()->create(['academic_year' => '2025-2026']);
        $application = AdmissionApplication::factory()->status(AdmissionStatus::Accepted)->create();

        $result = $this->actingAs($admin)->postJson("/api/admissions/{$application->id}/enroll", ['school_class_id' => $class->id])
            ->assertCreated()
            ->json('data');

        $this->assertCount(1, $this->sender()->sent());
        $body = $this->sender()->sent()[0]->body;
        $this->assertStringContainsString($result['student']['matricule'], $body);
        $this->assertStringNotContainsString($result['initial_password'], $body);
        $this->assertNotNull($result['application']['notified_at']);
    }

    #[Test]
    public function un_prestataire_en_panne_n_empeche_pas_la_decision(): void
    {
        $this->app->instance(NotificationSenderContract::class, new class implements NotificationSenderContract
        {
            public function send(NotificationMessage $message): bool
            {
                throw new RuntimeException('Operateur SMS indisponible');
            }
        });
        $admin = $this->userWithRole('admin');
        $application = AdmissionApplication::factory()->create();

        $this->actingAs($admin)->postJson("/api/admissions/{$application->id}/status", ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.notified_at', null);

        $this->assertDatabaseHas('notification_logs', [
            'admission_application_id' => $application->id,
            'status' => 'failed',
            'error' => 'Operateur SMS indisponible',
        ]);
    }

    #[Test]
    public function le_journal_des_notifications_liste_les_messages_d_admission_avec_leur_dossier(): void
    {
        $admin = $this->userWithRole('admin');
        $application = AdmissionApplication::factory()->create(['first_name' => 'Mariama', 'last_name' => 'Barry']);
        $this->actingAs($admin)->postJson("/api/admissions/{$application->id}/status", ['status' => 'accepted'])->assertOk();

        $this->actingAs($admin)->getJson('/api/notification-logs')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'admission')
            ->assertJsonPath('data.0.student', null)
            ->assertJsonPath('data.0.admission.name', 'Mariama Barry')
            ->assertJsonPath('data.0.admission.reference', $application->reference);
    }

    #[Test]
    public function supprimer_un_dossier_supprime_ses_messages(): void
    {
        $admin = $this->userWithRole('admin');
        $application = AdmissionApplication::factory()->create();
        $this->actingAs($admin)->postJson("/api/admissions/{$application->id}/status", ['status' => 'waitlisted'])->assertOk();

        $this->actingAs($admin)->deleteJson("/api/admissions/{$application->id}")->assertNoContent();

        $this->assertDatabaseCount('notification_logs', 0);
    }
}
