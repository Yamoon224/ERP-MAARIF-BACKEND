<?php

namespace Tests\Feature\Discipline;

use App\Domains\Notifications\Contracts\NotificationSenderContract;
use App\Domains\Notifications\Senders\ArrayNotificationSender;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SummonManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creer_une_convocation_notifie_immediatement_le_tuteur(): void
    {
        $admin = $this->userWithRole('admin');
        $student = Student::factory()->create(['guardian_email' => 'tuteur@example.test']);

        $response = $this->actingAs($admin)->postJson('/api/summons', [
            'student_id' => $student->id,
            'reason' => 'Retards repetes',
            'scheduled_at' => now()->addDays(3)->toIso8601String(),
            'location' => 'Bureau de la direction',
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->assertNotNull($response->json('data.notified_at'));

        /** @var ArrayNotificationSender $sender */
        $sender = $this->app->make(NotificationSenderContract::class);
        $this->assertCount(1, $sender->sent());
        $this->assertSame('tuteur@example.test', $sender->sent()[0]->recipient);

        $this->assertDatabaseHas('notification_logs', [
            'student_id' => $student->id,
            'type' => 'convocation',
            'status' => 'sent',
        ]);
    }

    #[Test]
    public function un_enseignant_ne_peut_pas_creer_de_convocation(): void
    {
        $teacher = $this->userWithRole('teacher');
        $student = Student::factory()->create();

        $this->actingAs($teacher)->postJson('/api/summons', [
            'student_id' => $student->id,
            'reason' => 'Motif',
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ])->assertStatus(403);
    }

    #[Test]
    public function le_parent_consulte_les_convocations_de_son_enfant_uniquement(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->studentWithPassword();
        $otherStudent = Student::factory()->create();

        $this->actingAs($admin)->postJson('/api/summons', [
            'student_id' => $student->id,
            'reason' => 'Motif A',
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ])->assertCreated();

        $this->actingAs($admin)->postJson('/api/summons', [
            'student_id' => $otherStudent->id,
            'reason' => 'Motif B',
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ])->assertCreated();

        $this->actingAs($student)->getJson('/api/parent/summons')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reason', 'Motif A');
    }
}
