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

/**
 * Filtres "annee scolaire / trimestre / mois" : chaque liste doit rendre la
 * meme reponse a la meme periode, quelle que soit la ressource.
 */
class PeriodFilterTest extends TestCase
{
    use BuildsSchoolYear;
    use RefreshDatabase;

    #[Test]
    public function les_annees_scolaires_sont_listees_avec_leurs_trimestres(): void
    {
        $admin = $this->userWithRole('admin');
        $this->schoolYear('2025-2026');
        $current = $this->schoolYear('2026-2027', 60000, '5eme A');
        $current['terms'][0]->update(['is_current' => true]);

        $this->actingAs($admin)->getJson('/api/academic-years')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.label', '2026-2027')
            ->assertJsonPath('data.0.is_current', true)
            ->assertJsonPath('data.0.starts_at', '2026-10-01')
            ->assertJsonPath('data.0.ends_at', '2027-06-30')
            ->assertJsonCount(3, 'data.0.terms')
            ->assertJsonPath('data.1.label', '2025-2026')
            ->assertJsonPath('data.1.is_current', false);
    }

    #[Test]
    public function le_parent_lit_le_calendrier_scolaire_sur_sa_propre_route_uniquement(): void
    {
        $student = $this->studentWithPassword();
        $this->schoolYear('2025-2026');

        $this->actingAs($student)->getJson('/api/parent/academic-years')
            ->assertOk()
            ->assertJsonPath('data.0.label', '2025-2026')
            ->assertJsonCount(3, 'data.0.terms');

        $this->actingAs($student)->getJson('/api/academic-years')->assertForbidden();
    }

    #[Test]
    public function les_presences_se_filtrent_par_mois_trimestre_et_annee(): void
    {
        $admin = $this->userWithRole('admin');
        $terms = $this->schoolYear('2025-2026')['terms'];
        $this->schoolYear('2026-2027', 60000, '5eme A');
        $student = Student::factory()->create();

        foreach (['2025-10-06', '2025-11-10', '2026-02-09', '2026-10-05'] as $date) {
            AttendanceRecord::factory()->create(['student_id' => $student->id, 'date' => $date, 'status' => 'absent']);
        }

        $this->actingAs($admin)->getJson('/api/attendance-records?month=2025-11')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.date', '2025-11-10');

        $this->actingAs($admin)->getJson("/api/attendance-records?term_id={$terms[0]->id}")
            ->assertOk()->assertJsonCount(2, 'data');

        $this->actingAs($admin)->getJson('/api/attendance-records?academic_year=2025-2026')
            ->assertOk()->assertJsonCount(3, 'data');

        $this->actingAs($admin)->getJson('/api/attendance-records?academic_year=2026-2027')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function un_mois_precise_l_emporte_sur_l_annee_choisie(): void
    {
        $admin = $this->userWithRole('admin');
        $this->schoolYear('2025-2026');
        $student = Student::factory()->create();
        AttendanceRecord::factory()->create(['student_id' => $student->id, 'date' => '2025-10-06', 'status' => 'absent']);
        AttendanceRecord::factory()->create(['student_id' => $student->id, 'date' => '2026-01-12', 'status' => 'absent']);

        $this->actingAs($admin)->getJson('/api/attendance-records?academic_year=2025-2026&month=2026-01')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.date', '2026-01-12');
    }

    #[Test]
    public function un_mois_mal_forme_est_refuse(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->getJson('/api/attendance-records?month=novembre')
            ->assertStatus(422)
            ->assertJsonValidationErrors('month');
    }

    #[Test]
    public function les_sanctions_et_convocations_se_rattachent_au_trimestre_par_leurs_dates(): void
    {
        $admin = $this->userWithRole('admin');
        $terms = $this->schoolYear('2025-2026')['terms'];
        $student = Student::factory()->create();

        Sanction::factory()->create(['student_id' => $student->id, 'created_by' => $admin->id, 'start_date' => '2025-11-03']);
        Sanction::factory()->create(['student_id' => $student->id, 'created_by' => $admin->id, 'start_date' => '2026-02-16']);
        Summon::factory()->create(['student_id' => $student->id, 'created_by' => $admin->id, 'scheduled_at' => '2026-05-20 09:00:00']);

        $this->actingAs($admin)->getJson("/api/sanctions?term_id={$terms[0]->id}")
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($admin)->getJson('/api/sanctions?academic_year=2025-2026')
            ->assertOk()->assertJsonCount(2, 'data');

        $this->actingAs($admin)->getJson("/api/summons?term_id={$terms[2]->id}")
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($admin)->getJson("/api/summons?term_id={$terms[0]->id}")
            ->assertOk()->assertJsonCount(0, 'data');
    }

    #[Test]
    public function les_notes_se_filtrent_par_annee_scolaire_et_par_mois_de_saisie(): void
    {
        $admin = $this->userWithRole('admin');
        $terms2025 = $this->schoolYear('2025-2026')['terms'];
        $terms2026 = $this->schoolYear('2026-2027', 60000, '5eme A')['terms'];
        $student = Student::factory()->create();
        $subject = Subject::factory()->create();

        Grade::factory()->create(['student_id' => $student->id, 'subject_id' => $subject->id, 'term_id' => $terms2025[0]->id, 'recorded_at' => '2025-11-05']);
        Grade::factory()->create(['student_id' => $student->id, 'subject_id' => $subject->id, 'term_id' => $terms2025[1]->id, 'recorded_at' => '2026-02-10']);
        Grade::factory()->create(['student_id' => $student->id, 'subject_id' => $subject->id, 'term_id' => $terms2026[0]->id, 'recorded_at' => '2026-11-05']);

        $this->actingAs($admin)->getJson('/api/grades?academic_year=2025-2026')
            ->assertOk()->assertJsonCount(2, 'data');

        $this->actingAs($admin)->getJson('/api/grades?academic_year=2025-2026&month=2026-02')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($admin)->getJson("/api/grades?term_id={$terms2026[0]->id}")
            ->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function le_parent_filtre_l_historique_de_son_enfant_par_periode(): void
    {
        $student = $this->studentWithPassword();
        $this->schoolYear('2025-2026');
        AttendanceRecord::factory()->create(['student_id' => $student->id, 'date' => '2025-10-06', 'status' => 'absent']);
        AttendanceRecord::factory()->create(['student_id' => $student->id, 'date' => '2026-01-12', 'status' => 'absent']);

        $this->actingAs($student)->getJson('/api/parent/attendance?month=2026-01')
            ->assertOk()->assertJsonCount(1, 'data');
    }
}
