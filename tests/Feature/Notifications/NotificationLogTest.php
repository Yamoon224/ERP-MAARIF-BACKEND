<?php

namespace Tests\Feature\Notifications;

use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Enums\NotificationType;
use App\Models\AdmissionApplication;
use App\Models\NotificationLog;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
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
