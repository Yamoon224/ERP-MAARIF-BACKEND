<?php

namespace Tests\Feature\Notifications;

use App\Domains\Notifications\Contracts\NotificationSenderContract;
use App\Domains\Notifications\DTOs\NotificationMessage;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Enums\NotificationType;
use App\Domains\Notifications\Senders\ArrayNotificationSender;
use App\Models\AdmissionApplication;
use App\Models\NotificationLog;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class NotificationLogTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, mixed>  $attributes */
    private function log(array $attributes = []): NotificationLog
    {
        return NotificationLog::create([
            'channel' => NotificationChannel::Email,
            'type' => NotificationType::Summon,
            'recipient' => 'tuteur@example.test',
            'subject' => 'Convocation',
            'body' => 'Corps du message',
            'status' => NotificationStatus::Sent,
            ...$attributes,
        ]);
    }

    #[Test]
    public function la_liste_renvoie_le_corps_du_message_et_son_destinataire_eleve_ou_dossier(): void
    {
        $admin = $this->userWithRole('admin');
        $student = Student::factory()->create(['first_name' => 'Awa', 'last_name' => 'Camara']);
        $application = AdmissionApplication::factory()->create(['first_name' => 'Mariama', 'last_name' => 'Barry']);
        $this->log(['student_id' => $student->id, 'body' => 'Convocation pour Awa']);
        $this->log(['admission_application_id' => $application->id, 'type' => NotificationType::Admission, 'body' => 'Admission de Mariama']);

        $response = $this->actingAs($admin)->getJson('/api/notification-logs')->assertOk()->assertJsonCount(2, 'data');

        $rows = collect($response->json('data'))->keyBy('type');
        $this->assertSame('Convocation pour Awa', $rows['convocation']['body']);
        $this->assertSame('Awa Camara', $rows['convocation']['student']['name']);
        $this->assertNull($rows['convocation']['admission']);
        $this->assertSame($application->reference, $rows['admission']['admission']['reference']);
        $this->assertNull($rows['admission']['student']);
    }

    #[Test]
    public function la_liste_se_filtre_par_statut_type_et_canal(): void
    {
        $admin = $this->userWithRole('admin');
        $student = Student::factory()->create();
        $this->log(['student_id' => $student->id]);
        $this->log(['student_id' => $student->id, 'status' => NotificationStatus::Failed, 'error' => 'Operateur indisponible']);
        $this->log(['student_id' => $student->id, 'type' => NotificationType::Sanction, 'channel' => NotificationChannel::Sms]);

        $this->actingAs($admin)->getJson('/api/notification-logs?status=failed')->assertJsonCount(1, 'data')->assertJsonPath('data.0.error', 'Operateur indisponible');
        $this->actingAs($admin)->getJson('/api/notification-logs?type=sanction')->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson('/api/notification-logs?channel=sms')->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'sanction');
        $this->actingAs($admin)->getJson('/api/notification-logs?type=convocation&status=sent')->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson('/api/notification-logs?status=inconnu')->assertStatus(422);
        $this->actingAs($admin)->getJson('/api/notification-logs?type=inconnu')->assertStatus(422);
    }

    #[Test]
    public function la_recherche_porte_sur_le_destinataire_l_eleve_et_le_dossier(): void
    {
        $admin = $this->userWithRole('admin');
        $student = Student::factory()->create(['first_name' => 'Awa', 'last_name' => 'Camara', 'matricule' => 'MAA-2026-000777']);
        $application = AdmissionApplication::factory()->create(['first_name' => 'Mariama', 'last_name' => 'Barry']);
        $this->log(['student_id' => $student->id, 'recipient' => 'camara@example.test']);
        $this->log(['admission_application_id' => $application->id, 'recipient' => 'alpha@example.test', 'type' => NotificationType::Admission]);

        $this->actingAs($admin)->getJson('/api/notification-logs?search=Camara')->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson('/api/notification-logs?search=000777')->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson('/api/notification-logs?search=alpha@')->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'admission');
        $this->actingAs($admin)->getJson("/api/notification-logs?search={$application->reference}")->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson('/api/notification-logs?search=Barry')->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson('/api/notification-logs?search=introuvable')->assertJsonCount(0, 'data');
    }

    #[Test]
    public function le_resume_compte_par_statut_sans_tenir_compte_du_filtre_de_statut(): void
    {
        $admin = $this->userWithRole('admin');
        $student = Student::factory()->create();
        $this->log(['student_id' => $student->id]);
        $this->log(['student_id' => $student->id]);
        $this->log(['student_id' => $student->id, 'status' => NotificationStatus::Failed]);
        $this->log(['student_id' => $student->id, 'type' => NotificationType::Sanction, 'status' => NotificationStatus::Pending]);

        $this->actingAs($admin)->getJson('/api/notification-logs/summary')
            ->assertOk()
            ->assertJsonPath('data.total', 4)
            ->assertJsonPath('data.by_status.sent', 2)
            ->assertJsonPath('data.by_status.failed', 1)
            ->assertJsonPath('data.by_status.pending', 1);

        // Le type restreint le resume ; le statut, non : les cartes restent lisibles en filtrant sur "echec".
        $this->actingAs($admin)->getJson('/api/notification-logs/summary?type=convocation&status=failed')
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.by_status.sent', 2)
            ->assertJsonPath('data.by_status.failed', 1);
    }

    private function sender(): ArrayNotificationSender
    {
        /** @var ArrayNotificationSender $sender */
        $sender = $this->app->make(NotificationSenderContract::class);

        return $sender;
    }

    #[Test]
    public function un_message_en_echec_est_renvoye_sur_la_meme_ligne_du_journal(): void
    {
        $admin = $this->userWithRole('admin');
        $student = Student::factory()->create(['guardian_email' => 'tuteur@example.test']);
        $log = $this->log([
            'student_id' => $student->id,
            'recipient' => 'tuteur@example.test',
            'body' => 'Convocation pour Awa',
            'status' => NotificationStatus::Failed,
            'error' => 'Operateur SMS indisponible',
        ]);

        $this->actingAs($admin)->postJson("/api/notification-logs/{$log->id}/resend")
            ->assertOk()
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.attempts', 2)
            ->assertJsonPath('data.error', null);

        $this->assertCount(1, $this->sender()->sent());
        $this->assertSame('Convocation pour Awa', $this->sender()->sent()[0]->body);
        $this->assertDatabaseCount('notification_logs', 1);
        $this->assertNotNull($log->refresh()->sent_at);
    }

    #[Test]
    public function un_renvoi_qui_echoue_de_nouveau_reste_en_echec_avec_la_nouvelle_raison(): void
    {
        $this->app->instance(NotificationSenderContract::class, new class implements NotificationSenderContract
        {
            public function send(NotificationMessage $message): bool
            {
                throw new RuntimeException('Toujours indisponible');
            }
        });
        $admin = $this->userWithRole('admin');
        $log = $this->log(['student_id' => Student::factory()->create()->id, 'status' => NotificationStatus::Failed, 'error' => 'Premiere panne']);

        $this->actingAs($admin)->postJson("/api/notification-logs/{$log->id}/resend")
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.attempts', 2)
            ->assertJsonPath('data.error', 'Toujours indisponible');

        $this->actingAs($admin)->postJson("/api/notification-logs/{$log->id}/resend")->assertOk()->assertJsonPath('data.attempts', 3);
    }

    #[Test]
    public function le_renvoi_utilise_le_contact_actuel_du_tuteur_apres_correction(): void
    {
        $admin = $this->userWithRole('admin');
        $student = Student::factory()->create(['guardian_email' => null, 'guardian_phone' => '+224111111111']);
        $log = $this->log([
            'student_id' => $student->id,
            'channel' => NotificationChannel::Sms,
            'recipient' => '+224111111111',
            'status' => NotificationStatus::Failed,
        ]);

        // Le numero etait faux : on le corrige, puis on renvoie.
        $student->update(['guardian_phone' => '+224622222222']);

        $this->actingAs($admin)->postJson("/api/notification-logs/{$log->id}/resend")
            ->assertOk()
            ->assertJsonPath('data.recipient', '+224622222222')
            ->assertJsonPath('data.channel', 'sms');
        $this->assertSame('+224622222222', $this->sender()->sent()[0]->recipient);

        // Et si une adresse e-mail a ete ajoutee entre-temps, elle prend le pas sur le SMS.
        $log->refresh()->update(['status' => NotificationStatus::Failed]);
        $student->update(['guardian_email' => 'nouveau@example.test']);

        $this->actingAs($admin)->postJson("/api/notification-logs/{$log->id}/resend")
            ->assertJsonPath('data.recipient', 'nouveau@example.test')
            ->assertJsonPath('data.channel', 'email');
    }

    #[Test]
    public function le_renvoi_d_une_decision_d_admission_renseigne_la_date_de_notification_du_dossier(): void
    {
        $admin = $this->userWithRole('admin');
        $application = AdmissionApplication::factory()->create(['notified_at' => null]);
        $log = $this->log([
            'admission_application_id' => $application->id,
            'type' => NotificationType::Admission,
            'status' => NotificationStatus::Failed,
        ]);

        $this->actingAs($admin)->postJson("/api/notification-logs/{$log->id}/resend")->assertOk()->assertJsonPath('data.status', 'sent');

        $this->assertNotNull($application->refresh()->notified_at);
    }

    #[Test]
    public function seul_un_message_en_echec_peut_etre_renvoye(): void
    {
        $admin = $this->userWithRole('admin');
        $student = Student::factory()->create();

        foreach ([NotificationStatus::Sent, NotificationStatus::Pending] as $status) {
            $log = $this->log(['student_id' => $student->id, 'status' => $status]);

            $this->actingAs($admin)->postJson("/api/notification-logs/{$log->id}/resend")
                ->assertStatus(409)
                ->assertJsonPath('error_code', 'notification_not_failed');
        }

        $this->assertCount(0, $this->sender()->sent());
        $this->actingAs($admin)->postJson('/api/notification-logs/00000000-0000-0000-0000-000000000000/resend')->assertNotFound();
    }

    #[Test]
    public function seul_le_role_autorise_renvoie_un_message(): void
    {
        $log = $this->log(['student_id' => Student::factory()->create()->id, 'status' => NotificationStatus::Failed]);
        $teacher = $this->userWithRole('teacher');
        $accountant = $this->userWithRole('accountant');
        $parent = $this->studentWithPassword();

        foreach ([$teacher, $accountant, $parent] as $account) {
            $this->actingAs($account)->postJson("/api/notification-logs/{$log->id}/resend")->assertForbidden();
        }

        $this->assertSame(NotificationStatus::Failed, $log->refresh()->status);
    }

    #[Test]
    public function seul_le_role_autorise_consulte_le_journal(): void
    {
        $this->log(['student_id' => Student::factory()->create()->id]);
        $teacher = $this->userWithRole('teacher');
        $accountant = $this->userWithRole('accountant');
        $parent = $this->studentWithPassword();

        foreach ([$teacher, $accountant, $parent] as $account) {
            $this->actingAs($account)->getJson('/api/notification-logs')->assertForbidden();
            $this->actingAs($account)->getJson('/api/notification-logs/summary')->assertForbidden();
        }
    }
}
