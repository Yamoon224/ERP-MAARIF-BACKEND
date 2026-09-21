<?php

namespace Tests\Feature\Grades;

use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsSchoolYear;
use Tests\TestCase;

class GradeManagementTest extends TestCase
{
    use BuildsSchoolYear, RefreshDatabase;

    #[Test]
    public function un_enseignant_saisit_une_note_qui_s_enregistre_a_son_nom(): void
    {
        $teacher = $this->userWithRole('teacher');
        ['terms' => $terms, 'class' => $class] = $this->schoolYear();
        $student = Student::factory()->create(['school_class_id' => $class->id]);
        $subject = Subject::factory()->create();
        $class->subjects()->attach($subject->id, ['teacher_id' => $teacher->id]);

        $response = $this->actingAs($teacher)->postJson('/api/grades', $this->payload($student, $subject, $terms[0]));

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

    #[Test]
    public function un_enseignant_ne_note_pas_une_matiere_qu_il_n_enseigne_pas_a_cette_classe(): void
    {
        $teacher = $this->userWithRole('teacher');
        $colleague = $this->userWithRole('teacher');
        ['terms' => $terms, 'class' => $class] = $this->schoolYear();
        $student = Student::factory()->create(['school_class_id' => $class->id]);
        $maths = Subject::factory()->create();
        $french = Subject::factory()->create();
        $class->subjects()->attach($maths->id, ['teacher_id' => $teacher->id]);
        $class->subjects()->attach($french->id, ['teacher_id' => $colleague->id]);

        $this->actingAs($teacher)->postJson('/api/grades', $this->payload($student, $french, $terms[0]))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'not_assigned_to_subject');

        $this->assertDatabaseCount('grades', 0);
    }

    #[Test]
    public function un_enseignant_ne_note_pas_un_eleve_d_une_classe_qui_n_est_pas_la_sienne(): void
    {
        $teacher = $this->userWithRole('teacher');
        ['terms' => $terms, 'class' => $mine] = $this->schoolYear();
        $other = SchoolClass::factory()->create(['academic_year' => '2025-2026']);
        $subject = Subject::factory()->create();
        $mine->subjects()->attach($subject->id, ['teacher_id' => $teacher->id]);
        $stranger = Student::factory()->create(['school_class_id' => $other->id]);

        // Même matière, même année : seule la classe de l'élève diffère.
        $this->actingAs($teacher)->postJson('/api/grades', $this->payload($stranger, $subject, $terms[0]))
            ->assertForbidden();
    }

    #[Test]
    public function une_affectation_d_une_annee_ne_donne_pas_le_droit_de_noter_l_annee_suivante(): void
    {
        $teacher = $this->userWithRole('teacher');
        ['class' => $class] = $this->schoolYear();
        ['terms' => $nextTerms] = $this->schoolYear('2026-2027', 60000, '5eme A');
        $student = Student::factory()->create(['school_class_id' => $class->id]);
        $subject = Subject::factory()->create();
        $class->subjects()->attach($subject->id, ['teacher_id' => $teacher->id]);

        $this->actingAs($teacher)->postJson('/api/grades', $this->payload($student, $subject, $nextTerms[0]))
            ->assertForbidden();
    }

    #[Test]
    public function un_enseignant_ne_corrige_ni_ne_supprime_que_ses_matieres(): void
    {
        $teacher = $this->userWithRole('teacher');
        $colleague = $this->userWithRole('teacher');
        ['terms' => $terms, 'class' => $class] = $this->schoolYear();
        $student = Student::factory()->create(['school_class_id' => $class->id]);
        $maths = Subject::factory()->create();
        $french = Subject::factory()->create();
        $class->subjects()->attach($maths->id, ['teacher_id' => $teacher->id]);
        $class->subjects()->attach($french->id, ['teacher_id' => $colleague->id]);
        $own = Grade::factory()->create(['student_id' => $student->id, 'subject_id' => $maths->id, 'term_id' => $terms[0]->id, 'value' => 10, 'max_value' => 20]);
        $theirs = Grade::factory()->create(['student_id' => $student->id, 'subject_id' => $french->id, 'term_id' => $terms[0]->id, 'value' => 10, 'max_value' => 20]);

        $this->actingAs($teacher)->putJson("/api/grades/{$own->id}", ['value' => 12])->assertOk()->assertJsonPath('data.value', 12);
        $this->actingAs($teacher)->putJson("/api/grades/{$theirs->id}", ['value' => 12])->assertForbidden();
        $this->actingAs($teacher)->deleteJson("/api/grades/{$theirs->id}")->assertForbidden();
        $this->actingAs($teacher)->deleteJson("/api/grades/{$own->id}")->assertNoContent();

        $this->assertDatabaseHas('grades', ['id' => $theirs->id, 'value' => 10]);
        $this->assertDatabaseMissing('grades', ['id' => $own->id]);
    }

    #[Test]
    public function un_administrateur_note_sans_etre_affecte(): void
    {
        $admin = $this->userWithRole('admin');
        ['terms' => $terms, 'class' => $class] = $this->schoolYear();
        $student = Student::factory()->create(['school_class_id' => $class->id]);
        $subject = Subject::factory()->create();

        $this->actingAs($admin)->postJson('/api/grades', $this->payload($student, $subject, $terms[0]))->assertCreated();
    }

    /** @return array<string, mixed> */
    private function payload(Student $student, Subject $subject, Term $term): array
    {
        return [
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'type' => 'devoir',
            'value' => 15,
            'max_value' => 20,
            'recorded_at' => now()->toDateString(),
        ];
    }
}
