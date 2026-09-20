<?php

namespace Tests\Feature\Students;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsSchoolYear;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use BuildsSchoolYear;
    use RefreshDatabase;

    #[Test]
    public function un_eleve_cree_dans_une_classe_est_inscrit_pour_l_annee_de_cette_classe(): void
    {
        $admin = $this->userWithRole('admin');
        $class = $this->schoolYear()['class'];

        $response = $this->actingAs($admin)->postJson('/api/students', [
            'first_name' => 'Aminata',
            'last_name' => 'Camara',
            'gender' => 'F',
            'guardian_name' => 'Fatou Camara',
            'guardian_phone' => '+224600000010',
            'school_class_id' => $class->id,
        ])->assertCreated();

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $response->json('data.id'),
            'school_class_id' => $class->id,
            'academic_year' => '2025-2026',
        ]);
    }

    #[Test]
    public function changer_un_eleve_de_classe_dans_la_meme_annee_met_a_jour_son_inscription(): void
    {
        $admin = $this->userWithRole('admin');
        $classA = $this->schoolYear()['class'];
        $classB = SchoolClass::factory()->create(['name' => '6eme B', 'academic_year' => '2025-2026']);
        $student = Student::factory()->create(['school_class_id' => $classA->id]);

        $this->actingAs($admin)->putJson("/api/students/{$student->id}", ['school_class_id' => $classB->id])->assertOk();

        $this->assertSame(1, Enrollment::where('student_id', $student->id)->count());
        $this->assertDatabaseHas('enrollments', ['student_id' => $student->id, 'school_class_id' => $classB->id]);
    }

    #[Test]
    public function la_reinscription_ouvre_une_nouvelle_annee_sans_effacer_l_historique(): void
    {
        $admin = $this->userWithRole('admin');
        $oldClass = $this->schoolYear('2025-2026')['class'];
        $newClass = $this->schoolYear('2026-2027', 60000, '5eme A')['class'];
        $student = Student::factory()->create(['school_class_id' => $oldClass->id]);

        $this->actingAs($admin)->postJson("/api/students/{$student->id}/enrollments", ['school_class_id' => $newClass->id])
            ->assertCreated()
            ->assertJsonPath('data.academic_year', '2026-2027')
            ->assertJsonPath('data.school_class.name', '5eme A');

        $this->assertSame(2, Enrollment::where('student_id', $student->id)->count());
        $this->assertSame($newClass->id, $student->refresh()->school_class_id);

        $this->actingAs($admin)->getJson("/api/students/{$student->id}/enrollments")
            ->assertOk()
            ->assertJsonPath('data.0.academic_year', '2026-2027')
            ->assertJsonPath('data.1.academic_year', '2025-2026');
    }

    #[Test]
    public function la_liste_des_eleves_se_filtre_par_annee_scolaire(): void
    {
        $admin = $this->userWithRole('admin');
        $class2025 = $this->schoolYear('2025-2026')['class'];
        $class2026 = $this->schoolYear('2026-2027', 60000, '5eme A')['class'];
        $inYear2025 = Student::factory()->create(['school_class_id' => $class2025->id]);
        Student::factory()->create(['school_class_id' => $class2026->id]);

        $this->actingAs($admin)->getJson('/api/students?academic_year=2025-2026')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inYear2025->id);
    }

    #[Test]
    public function l_effectif_d_une_classe_passee_survit_au_passage_de_ses_eleves_en_classe_superieure(): void
    {
        $admin = $this->userWithRole('admin');
        $oldClass = $this->schoolYear('2025-2026')['class'];
        $newClass = $this->schoolYear('2026-2027', 60000, '5eme A')['class'];
        $students = Student::factory()->count(3)->create(['school_class_id' => $oldClass->id]);

        foreach ($students as $student) {
            $this->actingAs($admin)->postJson("/api/students/{$student->id}/enrollments", ['school_class_id' => $newClass->id])->assertCreated();
        }

        $this->actingAs($admin)->getJson('/api/classes?academic_year=2025-2026')
            ->assertOk()
            ->assertJsonPath('data.0.students_count', 3);
        $this->actingAs($admin)->getJson('/api/classes?academic_year=2026-2027')
            ->assertOk()
            ->assertJsonPath('data.0.students_count', 3);
    }

    #[Test]
    public function un_enseignant_ne_peut_pas_reinscrire_un_eleve(): void
    {
        $teacher = $this->userWithRole('teacher');
        $class = $this->schoolYear()['class'];
        $student = Student::factory()->create();

        $this->actingAs($teacher)->postJson("/api/students/{$student->id}/enrollments", ['school_class_id' => $class->id])
            ->assertForbidden();
    }

    #[Test]
    public function un_eleve_avec_des_paiements_ne_peut_pas_etre_supprime(): void
    {
        $admin = $this->userWithRole('admin');
        $class = $this->schoolYear()['class'];
        $student = Student::factory()->create(['school_class_id' => $class->id]);
        Payment::factory()->create(['enrollment_id' => Enrollment::where('student_id', $student->id)->value('id')]);

        $this->actingAs($admin)->deleteJson("/api/students/{$student->id}")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'student_has_payments');

        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }
}
