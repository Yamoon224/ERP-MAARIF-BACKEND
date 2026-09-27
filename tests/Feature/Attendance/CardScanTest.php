<?php

namespace Tests\Feature\Attendance;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Pointage par carte scolaire (cahier des charges — pointage par QR code, option 2). */
class CardScanTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function un_surveillant_scanne_la_carte_d_un_eleve(): void
    {
        $staff = $this->userWithRole('teacher');
        $student = Student::factory()->create(['qr_token' => (string) Str::uuid()]);

        $this->actingAs($staff)->postJson('/api/attendance-records/scan-card', [
            'qr_token' => $student->qr_token,
        ])->assertCreated()->assertJsonPath('data.status', 'present')->assertJsonPath('data.source', 'card_scan');

        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $student->id,
            'status' => 'present',
            'source' => 'card_scan',
        ]);
    }

    #[Test]
    public function une_carte_inconnue_est_refusee(): void
    {
        $staff = $this->userWithRole('teacher');

        $this->actingAs($staff)->postJson('/api/attendance-records/scan-card', [
            'qr_token' => (string) Str::uuid(),
        ])->assertStatus(404)->assertJsonPath('error_code', 'unknown_card');
    }

    #[Test]
    public function un_eleve_inactif_est_refuse(): void
    {
        $staff = $this->userWithRole('teacher');
        $student = Student::factory()->create(['qr_token' => (string) Str::uuid(), 'is_active' => false]);

        $this->actingAs($staff)->postJson('/api/attendance-records/scan-card', [
            'qr_token' => $student->qr_token,
        ])->assertStatus(404)->assertJsonPath('error_code', 'unknown_card');
    }

    #[Test]
    public function le_scan_sans_permission_attendance_manage_est_refuse(): void
    {
        $accountant = $this->userWithRole('accountant');
        $student = Student::factory()->create(['qr_token' => (string) Str::uuid()]);

        $this->actingAs($accountant)->postJson('/api/attendance-records/scan-card', [
            'qr_token' => $student->qr_token,
        ])->assertForbidden();
    }
}
