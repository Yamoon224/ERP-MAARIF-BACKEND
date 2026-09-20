<?php

namespace Tests\Feature\Academics;

use App\Models\AttendanceRecord;
use App\Models\Grade;
use App\Models\Sanction;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Summon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsSchoolYear;
use Tests\TestCase;

/** Detail d'un trimestre : classes, matieres, eleves, notes, sanctions, convocations, presences. */
class TermOverviewTest extends TestCase
{
    use BuildsSchoolYear;
    use RefreshDatabase;

    #[Test]
    public function le_resume_du_trimestre_rassemble_tous_les_compteurs(): void
    {
        $admin = $this->userWithRole('admin');
        ['terms' => $terms, 'class' => $class] = $this->schoolYear();
        $subjectA = Subject::factory()->create(['coefficient' => 3]);
        $subjectB = Subject::factory()->create(['coefficient' => 1]);
        $student = Student::factory()->create(['school_class_id' => $class->id]);
        Student::factory()->create(['school_class_id' => $class->id]);

        // 18/20 en A (coef 3) et 10/20 en B (coef 1) => (18*3 + 10*1) / 4 = 16
        Grade::factory()->create(['student_id' => $student->id, 'subject_id' => $subjectA->id, 'term_id' => $terms[0]->id, 'value' => 18, 'max_value' => 20]);
        Grade::factory()->create(['student_id' => $student->id, 'subject_id' => $subjectB->id, 'term_id' => $terms[0]->id, 'value' => 10, 'max_value' => 20]);
        // Note d'un autre trimestre : ignoree.
        Grade::factory()->create(['student_id' => $student->id, 'subject_id' => $subjectA->id, 'term_id' => $terms[1]->id, 'value' => 2, 'max_value' => 20]);

        AttendanceRecord::factory()->create(['student_id' => $student->id, 'date' => '2025-11-04', 'status' => 'absent', 'justified' => false]);
        AttendanceRecord::factory()->create(['student_id' => $student->id, 'date' => '2025-11-05', 'status' => 'retard']);
        Sanction::factory()->create(['student_id' => $student->id, 'created_by' => $admin->id, 'start_date' => '2025-11-06']);
        Summon::factory()->create(['student_id' => $student->id, 'created_by' => $admin->id, 'scheduled_at' => '2025-12-01 09:00:00']);

        $this->actingAs($admin)->getJson("/api/terms/{$terms[0]->id}/overview")
            ->assertOk()
            ->assertJsonPath('data.classes', 1)
            ->assertJsonPath('data.subjects', 2)
            ->assertJsonPath('data.students', 2)
            ->assertJsonPath('data.grades', 2)
            ->assertJsonPath('data.average', 16)
            ->assertJsonPath('data.attendance.absent', 1)
            ->assertJsonPath('data.attendance.late', 1)
            ->assertJsonPath('data.sanctions', 1)
            ->assertJsonPath('data.summons', 1);
    }

    #[Test]
    public function un_enseignant_ne_voit_pas_les_compteurs_de_discipline(): void
    {
        $teacher = $this->userWithRole('teacher');
        $term = $this->schoolYear()['terms'][0];

        $this->actingAs($teacher)->getJson("/api/terms/{$term->id}/overview")
            ->assertOk()
            ->assertJsonPath('data.sanctions', null)
            ->assertJsonPath('data.summons', null);
    }

    #[Test]
    public function les_matieres_du_trimestre_affichent_classes_notes_et_moyenne(): void
    {
        $admin = $this->userWithRole('admin');
        ['terms' => $terms, 'class' => $class] = $this->schoolYear();
        $taught = Subject::factory()->create(['name' => 'Anglais']);
        $graded = Subject::factory()->create(['name' => 'Maths']);
        Subject::factory()->create(['name' => 'Inutilisee']);
        $teacher = $this->userWithRole('teacher');
        $class->subjects()->attach($taught->id, ['teacher_id' => $teacher->id]);
        $student = Student::factory()->create(['school_class_id' => $class->id]);
        Grade::factory()->create(['student_id' => $student->id, 'subject_id' => $graded->id, 'term_id' => $terms[0]->id, 'value' => 12, 'max_value' => 20]);
        Grade::factory()->create(['student_id' => $student->id, 'subject_id' => $graded->id, 'term_id' => $terms[0]->id, 'value' => 8, 'max_value' => 10]);

        $response = $this->actingAs($admin)->getJson("/api/terms/{$terms[0]->id}/subjects")->assertOk()->assertJsonCount(2, 'data');

        $rows = collect($response->json('data'))->keyBy('name');
        $this->assertSame(1, $rows['Anglais']['classes_count']);
        $this->assertNull($rows['Anglais']['average']);
        $this->assertSame(2, $rows['Maths']['grades_count']);
        $this->assertEquals(14.0, $rows['Maths']['average']); // (12/20 + 8/10) / 2 * 20
    }

    #[Test]
    public function les_eleves_du_trimestre_sont_ceux_inscrits_pour_l_annee_avec_moyenne_et_absences(): void
    {
        $admin = $this->userWithRole('admin');
        ['terms' => $terms, 'class' => $class] = $this->schoolYear();
        $other = $this->schoolYear('2026-2027', 60000, '5eme A');
        $subject = Subject::factory()->create();
        $enrolled = Student::factory()->create(['school_class_id' => $class->id, 'last_name' => 'Barry']);
        Student::factory()->create(['school_class_id' => $other['class']->id]);
        Grade::factory()->create(['student_id' => $enrolled->id, 'subject_id' => $subject->id, 'term_id' => $terms[0]->id, 'value' => 15, 'max_value' => 20]);
        AttendanceRecord::factory()->create(['student_id' => $enrolled->id, 'date' => '2025-11-04', 'status' => 'absent']);
        AttendanceRecord::factory()->create(['student_id' => $enrolled->id, 'date' => '2026-02-04', 'status' => 'absent']); // autre trimestre

        $this->actingAs($admin)->getJson("/api/terms/{$terms[0]->id}/students")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student.id', $enrolled->id)
            ->assertJsonPath('data.0.school_class.id', $class->id)
            ->assertJsonPath('data.0.average', 15)
            ->assertJsonPath('data.0.absences', 1)
            ->assertJsonPath('meta.total', 1);
    }

    #[Test]
    public function les_notes_du_trimestre_se_filtrent_par_classe(): void
    {
        $admin = $this->userWithRole('admin');
        ['terms' => $terms, 'class' => $class] = $this->schoolYear();
        $subject = Subject::factory()->create();
        $inClass = Student::factory()->create(['school_class_id' => $class->id]);
        $elsewhere = Student::factory()->create();
        foreach ([$inClass, $elsewhere] as $student) {
            Grade::factory()->create(['student_id' => $student->id, 'subject_id' => $subject->id, 'term_id' => $terms[0]->id]);
        }

        $this->actingAs($admin)->getJson("/api/grades?term_id={$terms[0]->id}&school_class_id={$class->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student.id', $inClass->id);
    }
}
