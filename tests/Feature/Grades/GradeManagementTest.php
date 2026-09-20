<?php

namespace Tests\Feature\Grades;

use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GradeManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function un_enseignant_saisit_une_note_qui_s_enregistre_a_son_nom(): void
    {
        $teacher = $this->userWithRole('teacher');
        $student = Student::factory()->create();
        $subject = Subject::factory()->create();
        $term = Term::factory()->create();

        $response = $this->actingAs($teacher)->postJson('/api/grades', [
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'type' => 'devoir',
            'value' => 15,
            'max_value' => 20,
            'recorded_at' => now()->toDateString(),
        ]);

        $response->assertCreated()->assertJsonPath('data.value', 15);
        $this->assertDatabaseHas('grades', ['student_id' => $student->id, 'teacher_id' => $teacher->id]);
    }

    #[Test]
    public function une_note_superieure_au_bareme_est_rejetee(): void
    {
        $teacher = $this->userWithRole('teacher');
        $student = Student::factory()->create();
        $subject = Subject::factory()->create();
        $term = Term::factory()->create();

        $this->actingAs($teacher)->postJson('/api/grades', [
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'type' => 'devoir',
            'value' => 25,
            'max_value' => 20,
            'recorded_at' => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('value');
    }

    #[Test]
    public function un_parent_ne_peut_pas_saisir_de_note(): void
    {
        $student = $this->studentWithPassword();
        $subject = Subject::factory()->create();
        $term = Term::factory()->create();

        $this->actingAs($student)->postJson('/api/grades', [
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'type' => 'devoir',
            'value' => 15,
            'max_value' => 20,
            'recorded_at' => now()->toDateString(),
        ])->assertStatus(403);
    }

    #[Test]
    public function la_liste_des_notes_se_filtre_par_eleve(): void
    {
        $teacher = $this->userWithRole('teacher');
        $student = Student::factory()->create();
        Grade::factory()->count(2)->create(['student_id' => $student->id]);
        Grade::factory()->count(3)->create();

        $this->actingAs($teacher)->getJson("/api/grades?student_id={$student->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
