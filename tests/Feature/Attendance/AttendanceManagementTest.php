<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttendanceManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function un_enseignant_pointe_un_eleve_absent(): void
    {
        $teacher = $this->userWithRole('teacher');
        $student = Student::factory()->create();

        $this->actingAs($teacher)->postJson('/api/attendance-records', [
            'student_id' => $student->id,
            'date' => now()->toDateString(),
            'status' => 'absent',
            'reason' => 'Maladie',
        ])->assertCreated()->assertJsonPath('data.status', 'absent');

        $this->assertDatabaseHas('attendance_records', ['student_id' => $student->id, 'status' => 'absent']);
    }

    /** Repointer le meme eleve le meme jour corrige l'enregistrement plutot que d'en creer un second. */
    #[Test]
    public function un_second_pointage_le_meme_jour_remplace_le_premier(): void
    {
        $teacher = $this->userWithRole('teacher');
        $student = Student::factory()->create();
        $today = now()->toDateString();

        $this->actingAs($teacher)->postJson('/api/attendance-records', [
            'student_id' => $student->id,
            'date' => $today,
            'status' => 'absent',
        ])->assertCreated();

        $this->actingAs($teacher)->postJson('/api/attendance-records', [
            'student_id' => $student->id,
            'date' => $today,
            'status' => 'present',
        ])->assertCreated();

        $this->assertSame(1, AttendanceRecord::where('student_id', $student->id)->count());
        $this->assertDatabaseHas('attendance_records', ['student_id' => $student->id, 'status' => 'present']);
    }

    #[Test]
    public function le_parent_consulte_l_historique_de_presence_de_son_enfant_uniquement(): void
    {
        $student = $this->studentWithPassword();
        $otherStudent = Student::factory()->create();

        AttendanceRecord::factory()->create(['student_id' => $student->id]);
        AttendanceRecord::factory()->create(['student_id' => $otherStudent->id]);

        $this->actingAs($student)->getJson('/api/parent/attendance')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student.id', $student->id);
    }
}
