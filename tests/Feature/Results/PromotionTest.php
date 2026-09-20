<?php

namespace Tests\Feature\Results;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsSchoolYear;
use Tests\TestCase;

/** Passage d'une classe a l'annee suivante d'apres les decisions de fin d'annee. */
class PromotionTest extends TestCase
{
    use BuildsSchoolYear, RefreshDatabase;

    private SchoolClass $source;

    private SchoolClass $upper;

    private SchoolClass $repeat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = $this->schoolYear('2025-2026', 50000, '6eme A')['class'];
        $this->upper = $this->schoolYear('2026-2027', 60000, '5eme A')['class'];
        $this->repeat = SchoolClass::factory()->create(['name' => '6eme A', 'level' => '6eme', 'academic_year' => '2026-2027']);
    }

    private function pupil(string $first, ?string $decision = null): Student
    {
        $student = Student::factory()->create(['first_name' => $first, 'school_class_id' => $this->source->id]);

        if ($decision !== null) {
            $enrollment = Enrollment::query()->where('student_id', $student->id)->where('academic_year', '2025-2026')->firstOrFail();
            $this->actingAs($this->userWithRole('admin'))
                ->putJson("/api/enrollments/{$enrollment->id}/decision", ['decision' => $decision])
                ->assertOk();
        }

        return $student;
    }

    private function enrolledClassFor(Student $student, string $year): ?string
    {
        return Enrollment::query()->where('student_id', $student->id)->where('academic_year', $year)->value('school_class_id');
    }

    /** @return array<string, mixed> */
    private function promote(array $payload): array
    {
        return $this->actingAs($this->userWithRole('admin'))
            ->postJson("/api/classes/{$this->source->id}/promotions", $payload)
            ->assertOk()
            ->json('data');
    }

    #[Test]
    public function les_admis_montent_les_redoublants_repetent_et_les_exclus_ne_sont_pas_reinscrits(): void
    {
        $admitted = $this->pupil('Awa', 'admitted');
        $repeating = $this->pupil('Bakary', 'repeat');
        $excluded = $this->pupil('Cheick', 'excluded');
        $undecided = $this->pupil('Dalanda');

        $summary = $this->promote(['admitted_class_id' => $this->upper->id, 'repeat_class_id' => $this->repeat->id]);

        $this->assertSame(
            ['promoted' => 1, 'repeated' => 1, 'excluded' => 1, 'undecided' => 1, 'already_enrolled' => 0, 'without_class' => 0, 'inactive' => 0],
            $summary,
        );
        $this->assertSame($this->upper->id, $this->enrolledClassFor($admitted, '2026-2027'));
        $this->assertSame($this->repeat->id, $this->enrolledClassFor($repeating, '2026-2027'));
        $this->assertNull($this->enrolledClassFor($excluded, '2026-2027'));
        $this->assertNull($this->enrolledClassFor($undecided, '2026-2027'));

        // L'historique est conserve, et la classe actuelle de l'eleve devient la nouvelle.
        $this->assertSame($this->source->id, $this->enrolledClassFor($admitted, '2025-2026'));
        $this->assertSame($this->upper->id, $admitted->refresh()->school_class_id);
    }

    #[Test]
    public function l_operation_est_rejouable_et_ne_deplace_pas_un_eleve_deja_place_a_la_main(): void
    {
        $admitted = $this->pupil('Awa', 'admitted');
        $manual = $this->pupil('Bakary', 'admitted');
        $otherUpper = SchoolClass::factory()->create(['name' => '5eme B', 'level' => '5eme', 'academic_year' => '2026-2027']);
        $this->actingAs($this->userWithRole('admin'))->postJson("/api/students/{$manual->id}/enrollments", ['school_class_id' => $otherUpper->id])->assertCreated();

        $first = $this->promote(['admitted_class_id' => $this->upper->id]);
        $second = $this->promote(['admitted_class_id' => $this->upper->id]);

        $this->assertSame(1, $first['promoted']);
        $this->assertSame(1, $first['already_enrolled']);
        $this->assertSame(0, $second['promoted']);
        $this->assertSame(2, $second['already_enrolled']);
        $this->assertSame($this->upper->id, $this->enrolledClassFor($admitted, '2026-2027'));
        $this->assertSame($otherUpper->id, $this->enrolledClassFor($manual, '2026-2027'));
    }

    #[Test]
    public function sans_classe_de_redoublement_les_redoublants_sont_signales_et_laisses_de_cote(): void
    {
        $repeating = $this->pupil('Bakary', 'repeat');

        $summary = $this->promote(['admitted_class_id' => $this->upper->id]);

        $this->assertSame(1, $summary['without_class']);
        $this->assertSame(0, $summary['repeated']);
        $this->assertNull($this->enrolledClassFor($repeating, '2026-2027'));
    }

    #[Test]
    public function un_eleve_inactif_n_est_pas_reinscrit(): void
    {
        $student = $this->pupil('Awa', 'admitted');
        $student->update(['is_active' => false]);

        $summary = $this->promote(['admitted_class_id' => $this->upper->id]);

        $this->assertSame(1, $summary['inactive']);
        $this->assertNull($this->enrolledClassFor($student, '2026-2027'));
    }

    #[Test]
    public function seules_les_decisions_enregistrees_comptent_pas_les_suggestions(): void
    {
        $student = $this->pupil('Awa');

        $summary = $this->promote(['admitted_class_id' => $this->upper->id]);

        $this->assertSame(1, $summary['undecided']);
        $this->assertNull($this->enrolledClassFor($student, '2026-2027'));
    }

    #[Test]
    public function les_classes_d_accueil_doivent_etre_d_une_annee_posterieure_et_d_une_meme_annee(): void
    {
        $admin = $this->userWithRole('admin');
        $sameYear = SchoolClass::factory()->create(['name' => '5eme Z', 'academic_year' => '2025-2026']);
        $laterYear = SchoolClass::factory()->create(['name' => '4eme A', 'level' => '4eme', 'academic_year' => '2027-2028']);

        $this->actingAs($admin)->postJson("/api/classes/{$this->source->id}/promotions", ['admitted_class_id' => $sameYear->id])
            ->assertStatus(422)->assertJsonPath('error_code', 'promotion_invalid_target');
        $this->actingAs($admin)->postJson("/api/classes/{$this->upper->id}/promotions", ['admitted_class_id' => $this->source->id])
            ->assertStatus(422)->assertJsonPath('error_code', 'promotion_invalid_target');
        $this->actingAs($admin)->postJson("/api/classes/{$this->source->id}/promotions", [
            'admitted_class_id' => $this->upper->id,
            'repeat_class_id' => $laterYear->id,
        ])->assertStatus(422)->assertJsonPath('error_code', 'promotion_invalid_target');

        $this->actingAs($admin)->postJson("/api/classes/{$this->source->id}/promotions", [])
            ->assertStatus(422)->assertJsonValidationErrors('admitted_class_id');
    }

    #[Test]
    public function seul_celui_qui_gere_les_resultats_peut_lancer_le_passage(): void
    {
        $teacher = $this->userWithRole('teacher');

        $this->actingAs($teacher)->postJson("/api/classes/{$this->source->id}/promotions", ['admitted_class_id' => $this->upper->id])
            ->assertForbidden();
    }
}
