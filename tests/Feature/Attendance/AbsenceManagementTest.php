<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsSchoolYear;
use Tests\TestCase;

/** Appel de classe, suivi et justification des absences. */
class AbsenceManagementTest extends TestCase
{
    use BuildsSchoolYear;
    use RefreshDatabase;

    /** @return array{class: SchoolClass, students: list<Student>} */
    private function classWithStudents(int $count = 3): array
    {
        $class = $this->schoolYear()['class'];
        $students = Student::factory()->count($count)->create(['school_class_id' => $class->id])->all();

        return ['class' => $class, 'students' => $students];
    }

    #[Test]
    public function la_feuille_d_appel_liste_les_eleves_actifs_de_la_classe_avec_leur_pointage_du_jour(): void
    {
        $teacher = $this->userWithRole('teacher');
        ['class' => $class, 'students' => $students] = $this->classWithStudents();
        Student::factory()->inactive()->create(['school_class_id' => $class->id]);
        Student::factory()->create(); // autre classe
        AttendanceRecord::factory()->create(['student_id' => $students[0]->id, 'date' => '2025-11-10', 'status' => 'absent']);

        $response = $this->actingAs($teacher)
            ->getJson("/api/attendance-records/roll-call?school_class_id={$class->id}&date=2025-11-10")
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $pointed = collect($response->json('data'))->filter(fn ($row) => $row['record'] !== null);
        $this->assertCount(1, $pointed);
        $this->assertSame('absent', $pointed->first()['record']['status']);
    }

    #[Test]
    public function l_appel_d_une_classe_est_enregistre_en_un_seul_envoi(): void
    {
        $teacher = $this->userWithRole('teacher');
        ['class' => $class, 'students' => $students] = $this->classWithStudents();

        $this->actingAs($teacher)->postJson('/api/attendance-records/bulk', [
            'school_class_id' => $class->id,
            'date' => '2025-11-10',
            'records' => [
                ['student_id' => $students[0]->id, 'status' => 'present'],
                ['student_id' => $students[1]->id, 'status' => 'absent', 'reason' => 'Maladie', 'justified' => true],
                ['student_id' => $students[2]->id, 'status' => 'retard'],
            ],
        ])->assertOk()->assertJsonCount(3, 'data');

        $this->assertSame(3, AttendanceRecord::count());
        $this->assertDatabaseHas('attendance_records', ['student_id' => $students[1]->id, 'status' => 'absent', 'justified' => true]);
    }

    #[Test]
    public function refaire_l_appel_le_meme_jour_corrige_les_pointages_sans_les_dupliquer(): void
    {
        $teacher = $this->userWithRole('teacher');
        ['class' => $class, 'students' => $students] = $this->classWithStudents(1);

        foreach (['absent', 'present'] as $status) {
            $this->actingAs($teacher)->postJson('/api/attendance-records/bulk', [
                'school_class_id' => $class->id,
                'date' => '2025-11-10',
                'records' => [['student_id' => $students[0]->id, 'status' => $status]],
            ])->assertOk();
        }

        $this->assertSame(1, AttendanceRecord::count());
        $this->assertDatabaseHas('attendance_records', ['student_id' => $students[0]->id, 'status' => 'present']);
    }

    #[Test]
    public function un_appel_refuse_un_eleve_qui_n_est_pas_dans_la_classe_et_n_enregistre_rien(): void
    {
        $teacher = $this->userWithRole('teacher');
        ['class' => $class, 'students' => $students] = $this->classWithStudents(1);
        $stranger = Student::factory()->create();

        $this->actingAs($teacher)->postJson('/api/attendance-records/bulk', [
            'school_class_id' => $class->id,
            'date' => '2025-11-10',
            'records' => [
                ['student_id' => $students[0]->id, 'status' => 'absent'],
                ['student_id' => $stranger->id, 'status' => 'absent'],
            ],
        ])->assertStatus(422)->assertJsonPath('error_code', 'student_not_in_class');

        $this->assertSame(0, AttendanceRecord::count());
    }

    #[Test]
    public function un_appel_dans_le_futur_est_refuse(): void
    {
        $teacher = $this->userWithRole('teacher');
        ['class' => $class, 'students' => $students] = $this->classWithStudents(1);

        $this->actingAs($teacher)->postJson('/api/attendance-records/bulk', [
            'school_class_id' => $class->id,
            'date' => now()->addDay()->toDateString(),
            'records' => [['student_id' => $students[0]->id, 'status' => 'absent']],
        ])->assertStatus(422)->assertJsonValidationErrors('date');
    }

    #[Test]
    public function une_absence_se_justifie_apres_coup(): void
    {
        $teacher = $this->userWithRole('teacher');
        $student = Student::factory()->create();
        $record = AttendanceRecord::factory()->create(['student_id' => $student->id, 'status' => 'absent', 'justified' => false]);

        $this->actingAs($teacher)->putJson("/api/attendance-records/{$record->id}", [
            'justified' => true,
            'reason' => 'Certificat medical',
        ])->assertOk()->assertJsonPath('data.justified', true)->assertJsonPath('data.reason', 'Certificat medical');
    }

    #[Test]
    public function le_bilan_compte_les_statuts_et_classe_les_eleves_les_plus_absents(): void
    {
        $teacher = $this->userWithRole('teacher');
        ['students' => $students] = $this->classWithStudents(2);

        $rows = [
            [$students[0], '2025-11-03', 'absent', false],
            [$students[0], '2025-11-04', 'absent', true],
            [$students[0], '2025-11-05', 'retard', false],
            [$students[1], '2025-11-03', 'absent', false],
            [$students[1], '2025-11-04', 'present', false],
            [$students[1], '2026-02-04', 'absent', false], // hors du mois filtre
        ];
        foreach ($rows as [$student, $date, $status, $justified]) {
            AttendanceRecord::factory()->create(['student_id' => $student->id, 'date' => $date, 'status' => $status, 'justified' => $justified]);
        }

        $response = $this->actingAs($teacher)->getJson('/api/attendance-records/summary?month=2025-11')->assertOk();

        $response->assertJsonPath('data.total', 5)
            ->assertJsonPath('data.present', 1)
            ->assertJsonPath('data.absent', 3)
            ->assertJsonPath('data.late', 1)
            ->assertJsonPath('data.justified_absences', 1)
            ->assertJsonPath('data.unjustified_absences', 2)
            ->assertJsonPath('data.top_absentees.0.student.id', $students[0]->id)
            ->assertJsonPath('data.top_absentees.0.absences', 2)
            ->assertJsonPath('data.top_absentees.0.unjustified', 1)
            ->assertJsonPath('data.top_absentees.0.lates', 1);
    }

    #[Test]
    public function la_liste_des_absences_se_filtre_par_justification_et_par_classe(): void
    {
        $teacher = $this->userWithRole('teacher');
        ['class' => $class, 'students' => $students] = $this->classWithStudents(1);
        $other = Student::factory()->create();

        AttendanceRecord::factory()->create(['student_id' => $students[0]->id, 'date' => '2025-11-03', 'status' => 'absent', 'justified' => true]);
        AttendanceRecord::factory()->create(['student_id' => $students[0]->id, 'date' => '2025-11-04', 'status' => 'absent', 'justified' => false]);
        AttendanceRecord::factory()->create(['student_id' => $other->id, 'date' => '2025-11-04', 'status' => 'absent', 'justified' => false]);

        $this->actingAs($teacher)->getJson("/api/attendance-records?school_class_id={$class->id}&status=absent&justified=0")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.date', '2025-11-04');
    }

    #[Test]
    public function le_comptable_n_a_pas_acces_a_l_appel(): void
    {
        $accountant = $this->userWithRole('accountant');

        $this->actingAs($accountant)->getJson('/api/attendance-records/summary')->assertForbidden();
    }
}
