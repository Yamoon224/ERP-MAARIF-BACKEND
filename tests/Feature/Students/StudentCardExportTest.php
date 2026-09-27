<?php

namespace Tests\Feature\Students;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Carte scolaire imprimable de l'eleve (cahier des charges — pointage par QR code, option 2). */
class StudentCardExportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function le_personnel_telecharge_la_carte_d_un_eleve(): void
    {
        $admin = $this->userWithRole('admin');
        $student = Student::factory()->create();

        $response = $this->actingAs($admin)->get("/api/students/{$student->id}/card");

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertNotNull($student->refresh()->qr_token);
    }

    #[Test]
    public function le_personnel_regenere_le_jeton_d_une_carte(): void
    {
        $admin = $this->userWithRole('admin');
        $student = Student::factory()->create(['qr_token' => 'ancien-jeton']);

        $this->actingAs($admin)->postJson("/api/students/{$student->id}/card/regenerate-token")->assertOk();

        $this->assertNotEquals('ancien-jeton', $student->refresh()->qr_token);
    }

    #[Test]
    public function le_telechargement_sans_permission_students_manage_est_refuse(): void
    {
        $teacher = $this->userWithRole('teacher');
        $student = Student::factory()->create();

        $this->actingAs($teacher)->get("/api/students/{$student->id}/card")->assertForbidden();
    }
}
