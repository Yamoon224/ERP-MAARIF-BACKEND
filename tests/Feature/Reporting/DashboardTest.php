<?php

namespace Tests\Feature\Reporting;

use App\Models\AttendanceRecord;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Grade;
use App\Models\Sanction;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Summon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsSchoolYear;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use BuildsSchoolYear;
    use RefreshDatabase;

    #[Test]
    public function le_tableau_de_bord_prend_l_annee_du_trimestre_courant_par_defaut(): void
    {
        $admin = $this->userWithRole('admin');
        $old = $this->schoolYear('2025-2026');
        $current = $this->schoolYear('2026-2027', 60000, '5eme A');
        $current['terms'][0]->update(['is_current' => true]);
        Student::factory()->create(['school_class_id' => $old['class']->id]);
        Student::factory()->count(2)->create(['school_class_id' => $current['class']->id]);

        $this->actingAs($admin)->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.academic_year', '2026-2027')
            ->assertJsonPath('data.students', 2)
            ->assertJsonPath('data.classes', 1);

        $this->actingAs($admin)->getJson('/api/dashboard?academic_year=2025-2026')
            ->assertOk()
            ->assertJsonPath('data.students', 1);
    }

    #[Test]
    public function les_indicateurs_suivent_le_trimestre_ou_le_mois_choisi(): void
    {
        $admin = $this->userWithRole('admin');
        ['terms' => $terms, 'class' => $class] = $this->schoolYear();
        $student = Student::factory()->create(['school_class_id' => $class->id]);
        $subject = Subject::factory()->create();

        Grade::factory()->create(['student_id' => $student->id, 'subject_id' => $subject->id, 'term_id' => $terms[0]->id, 'value' => 10, 'max_value' => 20, 'recorded_at' => '2025-11-05']);
        Grade::factory()->create(['student_id' => $student->id, 'subject_id' => $subject->id, 'term_id' => $terms[1]->id, 'value' => 18, 'max_value' => 20, 'recorded_at' => '2026-02-05']);
        AttendanceRecord::factory()->create(['student_id' => $student->id, 'date' => '2025-11-04', 'status' => 'absent', 'justified' => false]);
        AttendanceRecord::factory()->create(['student_id' => $student->id, 'date' => '2026-02-04', 'status' => 'absent', 'justified' => true]);
        Sanction::factory()->create(['student_id' => $student->id, 'created_by' => $admin->id, 'start_date' => '2025-11-06']);
        Summon::factory()->create(['student_id' => $student->id, 'created_by' => $admin->id, 'scheduled_at' => '2026-02-20 09:00:00']);

        $this->actingAs($admin)->getJson('/api/dashboard?academic_year=2025-2026')
            ->assertJsonPath('data.grades.count', 2)
            ->assertJsonPath('data.grades.average', 14)
            ->assertJsonPath('data.attendance.absent', 2)
            ->assertJsonPath('data.discipline.sanctions', 1)
            ->assertJsonPath('data.discipline.summons_pending', 1);

        $this->actingAs($admin)->getJson("/api/dashboard?academic_year=2025-2026&term_id={$terms[0]->id}")
            ->assertJsonPath('data.grades.average', 10)
            ->assertJsonPath('data.attendance.absent', 1)
            ->assertJsonPath('data.attendance.unjustified_absences', 1)
            ->assertJsonPath('data.discipline.summons', 0);

        $this->actingAs($admin)->getJson('/api/dashboard?academic_year=2025-2026&month=2026-02')
            ->assertJsonPath('data.grades.average', 18)
            ->assertJsonPath('data.attendance.unjustified_absences', 0)
            ->assertJsonPath('data.discipline.sanctions', 0)
            ->assertJsonPath('data.discipline.summons', 1);
    }

    #[Test]
    public function chaque_role_ne_recoit_que_les_blocs_auxquels_il_a_droit(): void
    {
        $this->schoolYear();

        $this->actingAs($this->userWithRole('teacher'))->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.discipline', null)
            ->assertJsonPath('data.accounting', null);

        $this->actingAs($this->userWithRole('accountant'))->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.discipline', null)
            ->assertJsonPath('data.accounting.collected', 0);

        $this->actingAs($this->userWithRole('admin'))->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonStructure(['data' => ['discipline' => ['sanctions'], 'accounting' => ['collected', 'arrears', 'recovery_rate']]]);
    }

    #[Test]
    public function les_graphiques_ont_leurs_donnees_effectif_par_classe_encaissements_et_depenses(): void
    {
        $admin = $this->userWithRole('admin');
        ['class' => $class] = $this->schoolYear('2025-2026', 50000, '6eme A');
        $other = SchoolClass::factory()->create(['name' => '5eme B', 'level' => '5eme', 'academic_year' => '2025-2026']);
        Student::factory()->count(3)->create(['school_class_id' => $class->id]);
        Student::factory()->create(['school_class_id' => $other->id]);

        $category = ExpenseCategory::factory()->create(['name' => 'Fournitures']);
        Expense::factory()->create(['expense_category_id' => $category->id, 'amount' => 120000, 'unit_price' => 120000, 'spent_at' => '2025-09-20']);
        Expense::factory()->create(['expense_category_id' => $category->id, 'amount' => 30000, 'unit_price' => 30000, 'spent_at' => '2025-11-04']);
        Expense::factory()->cancelled()->create(['expense_category_id' => $category->id, 'amount' => 777, 'unit_price' => 777, 'spent_at' => '2025-11-05']);

        $response = $this->actingAs($admin)->getJson('/api/dashboard?academic_year=2025-2026')->assertOk();

        // Effectif de chaque classe, par ordre alphabetique.
        $this->assertSame(['5eme B', '6eme A'], array_column($response->json('data.students_by_class'), 'name'));
        $this->assertSame([1, 3], array_column($response->json('data.students_by_class'), 'count'));

        $response->assertJsonPath('data.expenses.total', 150000)
            ->assertJsonPath('data.expenses.count', 2)
            ->assertJsonPath('data.expenses.by_category.0.name', 'Fournitures')
            ->assertJsonPath('data.expenses.by_category.0.total', 150000);
        $this->assertNotEmpty($response->json('data.expenses.by_month'));
        $this->assertNotEmpty($response->json('data.accounting.by_month'));
        $this->assertCount(4, $response->json('data.accounting.by_method'));
    }

    #[Test]
    public function les_depenses_ne_sont_visibles_qu_avec_le_droit_de_les_consulter(): void
    {
        $this->schoolYear();

        $this->actingAs($this->userWithRole('teacher'))->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.expenses', null);

        $this->actingAs($this->userWithRole('accountant'))->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.expenses.total', 0);
    }

    #[Test]
    public function sans_aucune_annee_le_tableau_de_bord_reste_utilisable(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.academic_year', null)
            ->assertJsonPath('data.students', 0)
            ->assertJsonPath('data.grades.average', null);
    }

    #[Test]
    public function le_parent_n_a_pas_acces_au_tableau_de_bord_du_personnel(): void
    {
        $this->actingAs($this->studentWithPassword())->getJson('/api/dashboard')->assertForbidden();
    }
}
