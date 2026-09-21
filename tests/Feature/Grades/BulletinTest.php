<?php

namespace Tests\Feature\Grades;

use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
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

    #[Test]
    public function le_personnel_exporte_le_bulletin_en_pdf(): void
    {
        $admin = $this->userWithRole('admin');
        $student = Student::factory()->create();
        $term = Term::factory()->create();
        $subject = Subject::factory()->create(['coefficient' => 2]);
        Grade::factory()->create(['student_id' => $student->id, 'term_id' => $term->id, 'subject_id' => $subject->id, 'value' => 18, 'max_value' => 20]);

        $response = $this->actingAs($admin)->get("/api/students/{$student->id}/bulletin/export?format=pdf&term_id={$term->id}");

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('.pdf', $response->headers->get('Content-Disposition'));
    }

    #[Test]
    public function le_personnel_exporte_le_bulletin_en_xlsx_avec_les_memes_chiffres_que_l_ecran(): void
    {
        $admin = $this->userWithRole('admin');
        $student = Student::factory()->create();
        $term = Term::factory()->create();
        $subject = Subject::factory()->create(['name' => 'Mathematiques', 'coefficient' => 2]);
        Grade::factory()->create(['student_id' => $student->id, 'term_id' => $term->id, 'subject_id' => $subject->id, 'value' => 18, 'max_value' => 20]);

        $response = $this->actingAs($admin)->get("/api/students/{$student->id}/bulletin/export?format=xlsx&term_id={$term->id}");

        $response->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'bulletin').'.xlsx';
        file_put_contents($file, $response->getContent());
        $sheet = IOFactory::load($file)->getActiveSheet();
        unlink($file);

        $this->assertSame($student->matricule, $sheet->getCell('B4')->getValue());
        $this->assertSame('Mathematiques', $sheet->getCell('A9')->getValue());
        $this->assertEquals(18, $sheet->getCell('E9')->getValue());
        $this->assertEquals(18, $sheet->getCell('E10')->getValue());
    }

    #[Test]
    public function un_format_d_export_inconnu_est_refuse(): void
    {
        $admin = $this->userWithRole('admin');
        $student = Student::factory()->create();

        $this->actingAs($admin)->getJson("/api/students/{$student->id}/bulletin/export?format=docx")
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');
    }

    #[Test]
    public function l_export_du_bulletin_reste_reserve_a_qui_peut_consulter_les_eleves(): void
    {
        $student = Student::factory()->create();
        $term = Term::factory()->create();
        $user = $this->userWithRole('teacher');
        $user->syncPermissions([]);
        $user->syncRoles([]);

        $this->actingAs($user)->getJson("/api/students/{$student->id}/bulletin/export?format=pdf&term_id={$term->id}")
            ->assertForbidden();
    }

    #[Test]
    public function le_parent_exporte_le_bulletin_de_son_enfant(): void
    {
        $student = $this->studentWithPassword();
        $term = Term::factory()->create(['is_current' => true]);
        $subject = Subject::factory()->create();
        Grade::factory()->create(['student_id' => $student->id, 'term_id' => $term->id, 'subject_id' => $subject->id, 'value' => 12, 'max_value' => 20]);

        $response = $this->actingAs($student)->get('/api/parent/bulletin/export?format=pdf');

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    #[Test]
    public function sans_trimestre_courant_l_export_du_parent_recoit_une_erreur_explicite(): void
    {
        $student = $this->studentWithPassword();

        $this->actingAs($student)->getJson('/api/parent/bulletin/export?format=pdf')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'no_current_term');
    }
}
