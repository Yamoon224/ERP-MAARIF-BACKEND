<?php

namespace Tests\Feature\Admissions;

use App\Domains\Admissions\Enums\AdmissionStatus;
use App\Models\AdmissionApplication;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdmissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'academic_year' => '2025-2026',
            'level' => '6eme',
            'first_name' => 'Mariama',
            'last_name' => 'Barry',
            'gender' => 'F',
            'birth_date' => '2014-05-12',
            'previous_school' => 'École primaire de Kaloum',
            'guardian_name' => 'Alpha Barry',
            'guardian_phone' => '+224620000000',
            'guardian_email' => 'alpha@example.com',
            'address' => 'Conakry',
            ...$overrides,
        ];
    }

    #[Test]
    public function l_administrateur_depose_une_candidature_qui_recoit_une_reference_et_le_statut_en_attente(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->postJson('/api/admissions', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.status_label', 'En attente')
            ->assertJsonPath('data.full_name', 'Mariama Barry')
            ->assertJsonPath('data.reference', 'ADM-'.now()->year.'-000001');

        $this->actingAs($admin)->postJson('/api/admissions', $this->payload(['first_name' => 'Ibrahima']))
            ->assertCreated()
            ->assertJsonPath('data.reference', 'ADM-'.now()->year.'-000002');
    }

    #[Test]
    public function une_candidature_incomplete_est_refusee(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->postJson('/api/admissions', ['first_name' => 'Mariama'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['academic_year', 'level', 'last_name', 'gender', 'guardian_name', 'guardian_phone']);

        $this->actingAs($admin)->postJson('/api/admissions', $this->payload(['academic_year' => '2025']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('academic_year');
    }

    #[Test]
    public function le_dossier_est_etudie_puis_admis_ou_mis_en_liste_d_attente(): void
    {
        $admin = $this->userWithRole('admin');
        $application = AdmissionApplication::factory()->create();

        $this->actingAs($admin)->postJson("/api/admissions/{$application->id}/status", ['status' => 'under_review'])
            ->assertOk()
            ->assertJsonPath('data.status', 'under_review');

        $this->actingAs($admin)->postJson("/api/admissions/{$application->id}/status", ['status' => 'accepted', 'note' => 'Bon dossier'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.decision_note', 'Bon dossier')
            ->assertJsonPath('data.decided_by.id', $admin->id);

        $this->actingAs($admin)->postJson("/api/admissions/{$application->id}/status", ['status' => 'waitlisted'])
            ->assertOk()
            ->assertJsonPath('data.status', 'waitlisted');
    }

    #[Test]
    public function un_refus_doit_etre_motive(): void
    {
        $admin = $this->userWithRole('admin');
        $application = AdmissionApplication::factory()->create();

        $this->actingAs($admin)->postJson("/api/admissions/{$application->id}/status", ['status' => 'rejected'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('note');

        $this->actingAs($admin)->postJson("/api/admissions/{$application->id}/status", ['status' => 'rejected', 'note' => 'Classe complète'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }

    #[Test]
    public function on_ne_peut_pas_forcer_le_statut_inscrit_ni_revenir_a_en_attente(): void
    {
        $admin = $this->userWithRole('admin');
        $application = AdmissionApplication::factory()->create();

        foreach (['enrolled', 'pending', 'inconnu'] as $status) {
            $this->actingAs($admin)->postJson("/api/admissions/{$application->id}/status", ['status' => $status])
                ->assertStatus(422)
                ->assertJsonValidationErrors('status');
        }
    }

    #[Test]
    public function une_candidature_admise_devient_un_eleve_inscrit_dans_la_classe_choisie(): void
    {
        $admin = $this->userWithRole('admin');
        $class = SchoolClass::factory()->create(['academic_year' => '2025-2026']);
        $application = AdmissionApplication::factory()->status(AdmissionStatus::Accepted)->create([
            'first_name' => 'Mariama',
            'last_name' => 'Barry',
            'guardian_phone' => '+224620000000',
        ]);

        $response = $this->actingAs($admin)->postJson("/api/admissions/{$application->id}/enroll", ['school_class_id' => $class->id])
            ->assertCreated()
            ->assertJsonPath('data.application.status', 'enrolled')
            ->assertJsonPath('data.student.first_name', 'Mariama')
            ->assertJsonPath('data.student.guardian_phone', '+224620000000');

        $this->assertNotEmpty($response->json('data.initial_password'));

        $student = Student::query()->findOrFail($response->json('data.student.id'));
        $this->assertMatchesRegularExpression('/^MAA-\d{4}-\d{6}$/', $student->matricule);
        $this->assertSame($class->id, $student->school_class_id);
        $this->assertDatabaseHas('enrollments', ['student_id' => $student->id, 'school_class_id' => $class->id, 'academic_year' => '2025-2026']);

        $application->refresh();
        $this->assertSame(AdmissionStatus::Enrolled, $application->status);
        $this->assertSame($student->id, $application->student_id);
        $this->assertNotNull($application->enrolled_at);
    }

    #[Test]
    public function le_mot_de_passe_initial_permet_la_connexion_au_portail_parent(): void
    {
        $admin = $this->userWithRole('admin');
        $class = SchoolClass::factory()->create(['academic_year' => '2025-2026']);
        $application = AdmissionApplication::factory()->status(AdmissionStatus::Accepted)->create();

        $result = $this->actingAs($admin)->postJson("/api/admissions/{$application->id}/enroll", ['school_class_id' => $class->id])->json('data');

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/parent/login', [
            'matricule' => $result['student']['matricule'],
            'password' => $result['initial_password'],
        ])->assertOk();
    }

    #[Test]
    public function seule_une_candidature_admise_peut_etre_inscrite(): void
    {
        $admin = $this->userWithRole('admin');
        $class = SchoolClass::factory()->create(['academic_year' => '2025-2026']);

        foreach ([AdmissionStatus::Pending, AdmissionStatus::UnderReview, AdmissionStatus::Waitlisted, AdmissionStatus::Rejected] as $status) {
            $application = AdmissionApplication::factory()->status($status)->create();

            $this->actingAs($admin)->postJson("/api/admissions/{$application->id}/enroll", ['school_class_id' => $class->id])
                ->assertStatus(422)
                ->assertJsonPath('error_code', 'admission_not_accepted');
        }

        $this->assertDatabaseCount('students', 0);
    }

    #[Test]
    public function la_classe_doit_appartenir_a_l_annee_de_la_candidature(): void
    {
        $admin = $this->userWithRole('admin');
        $wrongYear = SchoolClass::factory()->create(['academic_year' => '2026-2027']);
        $application = AdmissionApplication::factory()->status(AdmissionStatus::Accepted)->create(['academic_year' => '2025-2026']);

        $this->actingAs($admin)->postJson("/api/admissions/{$application->id}/enroll", ['school_class_id' => $wrongYear->id])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'admission_class_year_mismatch');

        $this->assertDatabaseCount('students', 0);
        $this->assertSame(AdmissionStatus::Accepted, $application->refresh()->status);
    }

    #[Test]
    public function un_dossier_inscrit_est_fige(): void
    {
        $admin = $this->userWithRole('admin');
        $class = SchoolClass::factory()->create(['academic_year' => '2025-2026']);
        $application = AdmissionApplication::factory()->status(AdmissionStatus::Accepted)->create();
        $this->actingAs($admin)->postJson("/api/admissions/{$application->id}/enroll", ['school_class_id' => $class->id])->assertCreated();

        $this->actingAs($admin)->postJson("/api/admissions/{$application->id}/enroll", ['school_class_id' => $class->id])
            ->assertStatus(409)->assertJsonPath('error_code', 'admission_already_enrolled');
        $this->actingAs($admin)->putJson("/api/admissions/{$application->id}", ['first_name' => 'Autre'])->assertStatus(409);
        $this->actingAs($admin)->postJson("/api/admissions/{$application->id}/status", ['status' => 'rejected', 'note' => 'x'])->assertStatus(409);
        $this->actingAs($admin)->deleteJson("/api/admissions/{$application->id}")->assertStatus(409);

        $this->assertSame(1, Student::query()->count());
        $this->assertSame(1, Enrollment::query()->count());
    }

    #[Test]
    public function un_dossier_non_inscrit_se_modifie_et_se_supprime(): void
    {
        $admin = $this->userWithRole('admin');
        $application = AdmissionApplication::factory()->create(['guardian_name' => 'Ancien']);

        $this->actingAs($admin)->putJson("/api/admissions/{$application->id}", ['guardian_name' => 'Nouveau tuteur'])
            ->assertOk()
            ->assertJsonPath('data.guardian_name', 'Nouveau tuteur');

        $this->actingAs($admin)->deleteJson("/api/admissions/{$application->id}")->assertNoContent();
        $this->assertDatabaseMissing('admission_applications', ['id' => $application->id]);
    }

    #[Test]
    public function la_liste_se_filtre_par_statut_annee_et_recherche(): void
    {
        $admin = $this->userWithRole('admin');
        AdmissionApplication::factory()->create(['first_name' => 'Mariama', 'last_name' => 'Barry']);
        AdmissionApplication::factory()->status(AdmissionStatus::Accepted)->create(['first_name' => 'Ousmane', 'last_name' => 'Diallo']);
        AdmissionApplication::factory()->create(['first_name' => 'Fanta', 'last_name' => 'Camara', 'academic_year' => '2026-2027']);

        $this->actingAs($admin)->getJson('/api/admissions')->assertOk()->assertJsonCount(3, 'data');
        $this->actingAs($admin)->getJson('/api/admissions?status=accepted')->assertJsonCount(1, 'data')->assertJsonPath('data.0.last_name', 'Diallo');
        $this->actingAs($admin)->getJson('/api/admissions?academic_year=2026-2027')->assertJsonCount(1, 'data')->assertJsonPath('data.0.last_name', 'Camara');
        $this->actingAs($admin)->getJson('/api/admissions?search=Barry')->assertJsonCount(1, 'data')->assertJsonPath('data.0.first_name', 'Mariama');
        $this->actingAs($admin)->getJson('/api/admissions?status=inconnu')->assertStatus(422);
    }

    #[Test]
    public function le_resume_compte_les_dossiers_par_statut_y_compris_a_zero(): void
    {
        $admin = $this->userWithRole('admin');
        AdmissionApplication::factory()->count(2)->create();
        AdmissionApplication::factory()->status(AdmissionStatus::Rejected)->create();
        AdmissionApplication::factory()->create(['academic_year' => '2026-2027']);

        $this->actingAs($admin)->getJson('/api/admissions/summary?academic_year=2025-2026')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.by_status.pending', 2)
            ->assertJsonPath('data.by_status.rejected', 1)
            ->assertJsonPath('data.by_status.accepted', 0)
            ->assertJsonPath('data.by_status.enrolled', 0);
    }

    #[Test]
    public function les_autres_roles_et_le_portail_parent_n_ont_pas_acces_aux_admissions(): void
    {
        $teacher = $this->userWithRole('teacher');
        $accountant = $this->userWithRole('accountant');
        $parent = $this->studentWithPassword();

        foreach ([$teacher, $accountant, $parent] as $account) {
            $this->actingAs($account)->getJson('/api/admissions')->assertForbidden();
            $this->actingAs($account)->postJson('/api/admissions', $this->payload())->assertForbidden();
        }
    }
}
