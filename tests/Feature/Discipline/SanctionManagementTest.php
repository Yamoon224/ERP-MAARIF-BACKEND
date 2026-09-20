<?php

namespace Tests\Feature\Discipline;

use App\Domains\Notifications\Contracts\NotificationSenderContract;
use App\Domains\Notifications\Senders\ArrayNotificationSender;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SanctionManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creer_une_sanction_notifie_le_tuteur_par_sms_si_aucun_e_mail_n_est_connu(): void
    {
        $admin = $this->userWithRole('admin');
        $student = Student::factory()->create(['guardian_email' => null, 'guardian_phone' => '+224612340000']);

        $response = $this->actingAs($admin)->postJson('/api/sanctions', [
            'student_id' => $student->id,
            'type' => 'avertissement',
            'reason' => 'Comportement perturbateur',
            'start_date' => now()->toDateString(),
        ]);

        $response->assertCreated()->assertJsonPath('data.type', 'avertissement');

        /** @var ArrayNotificationSender $sender */
        $sender = $this->app->make(NotificationSenderContract::class);
        $this->assertCount(1, $sender->sent());
        $this->assertSame('sms', $sender->sent()[0]->channel->value);
        $this->assertSame('+224612340000', $sender->sent()[0]->recipient);
    }

    #[Test]
    public function l_historique_des_sanctions_d_un_eleve_est_consultable_par_le_personnel(): void
    {
        $admin = $this->userWithRole('admin');
        $student = Student::factory()->create();

        $this->actingAs($admin)->postJson('/api/sanctions', [
            'student_id' => $student->id,
            'type' => 'renvoi_definitif',
            'reason' => 'Recidive grave',
            'start_date' => now()->toDateString(),
        ])->assertCreated();

        $this->actingAs($admin)->getJson("/api/sanctions?student_id={$student->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'renvoi_definitif');
    }
}
