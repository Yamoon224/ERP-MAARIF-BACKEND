<?php

namespace Tests\Feature\Grades;

use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BulletinTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function le_personnel_consulte_le_bulletin_de_n_importe_quel_eleve(): void
    {
        $admin = $this->userWithRole('admin');
        $student = Student::factory()->create();
        $term = Term::factory()->create();
        $subject = Subject::factory()->create(['coefficient' => 2]);
        Grade::factory()->create(['student_id' => $student->id, 'term_id' => $term->id, 'subject_id' => $subject->id, 'value' => 18, 'max_value' => 20]);

        $this->actingAs($admin)->getJson("/api/students/{$student->id}/bulletin?term_id={$term->id}")
            ->assertOk()
            ->assertJsonPath('data.overall_average', 18);
    }

    #[Test]
    public function le_parent_consulte_le_bulletin_de_son_enfant_sans_fournir_d_identifiant(): void
    {
        $student = $this->studentWithPassword();
        $term = Term::factory()->create(['is_current' => true]);
        $subject = Subject::factory()->create();
        Grade::factory()->create(['student_id' => $student->id, 'term_id' => $term->id, 'subject_id' => $subject->id, 'value' => 12, 'max_value' => 20]);

        $this->actingAs($student)->getJson('/api/parent/bulletin')
            ->assertOk()
            ->assertJsonPath('data.student.id', $student->id)
            ->assertJsonPath('data.overall_average', 12);
    }

    #[Test]
    public function sans_trimestre_courant_configure_le_parent_recoit_une_erreur_explicite(): void
    {
        $student = $this->studentWithPassword();

        $this->actingAs($student)->getJson('/api/parent/bulletin')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'no_current_term');
    }
}
