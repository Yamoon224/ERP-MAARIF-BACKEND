<?php

namespace Tests\Feature\Results;

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsSchoolYear;
use Tests\TestCase;

/**
 * Resultats par trimestre, semestre (1 = T1+T2, 2 = T2+T3) et annee.
 */
class ResultsTest extends TestCase
{
    use BuildsSchoolYear, RefreshDatabase;

    private SchoolClass $class;

    /** @var list<Term> */
    private array $terms;

    private Subject $math;

    private Subject $french;

    protected function setUp(): void
    {
        parent::setUp();

        ['terms' => $this->terms, 'class' => $this->class] = $this->schoolYear();
        $this->math = Subject::factory()->create(['name' => 'Mathematiques', 'coefficient' => 2]);
        $this->french = Subject::factory()->create(['name' => 'Francais', 'coefficient' => 1]);
    }

    private function pupil(string $first = 'Awa'): Student
    {
        return Student::factory()->create(['first_name' => $first, 'school_class_id' => $this->class->id]);
    }

    private function grade(Student $student, Term $term, Subject $subject, float $value, float $max = 20): void
    {
        Grade::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'value' => $value,
            'max_value' => $max,
        ]);
    }

    /**
     * @param  array<string, mixed>  $results
     * @return array<string, mixed>
     */
    private function period(array $results, string $key): array
    {
        return collect($results['periods'])->firstWhere('key', $key);
    }

    #[Test]
    public function le_classement_d_un_trimestre_ordonne_les_eleves_par_moyenne_ponderee(): void
    {
        $admin = $this->userWithRole('admin');
        [$first] = $this->terms;
        $best = $this->pupil('Awa');
        $average = $this->pupil('Bakary');
        $absent = $this->pupil('Cissé');

        $this->grade($best, $first, $this->math, 18);
        $this->grade($best, $first, $this->french, 12);
        $this->grade($average, $first, $this->math, 10);
        $this->grade($average, $first, $this->french, 10);

        $response = $this->actingAs($admin)
            ->getJson("/api/results?school_class_id={$this->class->id}&period=term&term_id={$first->id}")
            ->assertOk()
            ->assertJsonPath('data.period.kind', 'term')
            ->assertJsonPath('data.stats.students', 3)
            ->assertJsonPath('data.stats.ranked', 2)
            ->assertJsonPath('data.stats.average', 13)
            ->assertJsonPath('data.stats.highest', 16)
            ->assertJsonPath('data.stats.lowest', 10)
            ->assertJsonPath('data.stats.pass_rate', 100);

        $rows = collect($response->json('data.rows'));
        $this->assertSame([$best->id, $average->id, $absent->id], $rows->pluck('student.id')->all());
        $this->assertSame([1, 2, null], $rows->pluck('rank')->all());
        $this->assertSame([16, 10, null], $rows->pluck('average')->all());
        $this->assertSame('Très bien', $rows[0]['mention']);
        $this->assertNull($rows[2]['mention']);
    }

    #[Test]
    public function une_note_sur_10_est_ramenee_sur_20_avant_la_moyenne(): void
    {
        $admin = $this->userWithRole('admin');
        [$first] = $this->terms;
        $pupil = $this->pupil();
        $this->grade($pupil, $first, $this->math, 7, 10);

        $this->actingAs($admin)
            ->getJson("/api/results?school_class_id={$this->class->id}&period=term&term_id={$first->id}")
            ->assertJsonPath('data.rows.0.average', 14);
    }

    #[Test]
    public function les_semestres_regroupent_t1_t2_puis_t2_t3_et_l_annee_les_trois_trimestres(): void
    {
        $admin = $this->userWithRole('admin');
        [$first, $second, $third] = $this->terms;
        $pupil = $this->pupil();

        $this->grade($pupil, $first, $this->math, 10);
        $this->grade($pupil, $second, $this->math, 14);
        $this->grade($pupil, $third, $this->math, 18);

        $results = $this->actingAs($admin)
            ->getJson("/api/students/{$pupil->id}/results?academic_year=2025-2026")
            ->assertOk()
            ->json('data');

        $this->assertSame(10.0, (float) $this->period($results, $first->id)['average']);
        $this->assertSame(12.0, (float) $this->period($results, 'semester-1')['average']);
        $this->assertSame(16.0, (float) $this->period($results, 'semester-2')['average']);
        $this->assertSame(14.0, (float) $this->period($results, 'annual')['average']);
        $this->assertSame(
            [$first->id, $second->id, $third->id, 'semester-1', 'semester-2', 'annual'],
            array_column($results['periods'], 'key'),
        );
    }

    #[Test]
    public function chaque_trimestre_pese_autant_quel_que_soit_son_nombre_de_notes(): void
    {
        $admin = $this->userWithRole('admin');
        [$first, $second] = $this->terms;
        $pupil = $this->pupil();

        // T1 : moyenne 15 sur deux notes ; T2 : une seule note a 5.
        $this->grade($pupil, $first, $this->math, 10);
        $this->grade($pupil, $first, $this->math, 20);
        $this->grade($pupil, $second, $this->math, 5);

        $results = $this->actingAs($admin)->getJson("/api/students/{$pupil->id}/results?academic_year=2025-2026")->json('data');

        // (15 + 5) / 2 = 10, et non 35 / 3 = 11,67 si les notes etaient mises en commun.
        $this->assertSame(10.0, (float) $this->period($results, 'semester-1')['average']);
    }

    #[Test]
    public function un_trimestre_sans_note_dans_une_matiere_est_ignore_plutot_que_compte_pour_zero(): void
    {
        $admin = $this->userWithRole('admin');
        [$first, , $third] = $this->terms;
        $pupil = $this->pupil();

        $this->grade($pupil, $first, $this->math, 12);
        $this->grade($pupil, $third, $this->math, 16);

        $results = $this->actingAs($admin)->getJson("/api/students/{$pupil->id}/results?academic_year=2025-2026")->json('data');

        $this->assertSame(14.0, (float) $this->period($results, 'annual')['average']);
        $this->assertNull($this->period($results, $this->terms[1]->id)['average']);
    }

    #[Test]
    public function les_ex_aequo_partagent_le_meme_rang(): void
    {
        $admin = $this->userWithRole('admin');
        [$first] = $this->terms;
        [$a, $b, $c] = [$this->pupil('Awa'), $this->pupil('Binta'), $this->pupil('Cheick')];

        $this->grade($a, $first, $this->math, 15);
        $this->grade($b, $first, $this->math, 15);
        $this->grade($c, $first, $this->math, 12);

        $ranks = $this->actingAs($admin)
            ->getJson("/api/results?school_class_id={$this->class->id}&period=term&term_id={$first->id}")
            ->json('data.rows.*.rank');

        $this->assertSame([1, 1, 3], $ranks);
    }

    #[Test]
    public function le_rang_d_un_eleve_se_calcule_dans_sa_classe_seulement(): void
    {
        $admin = $this->userWithRole('admin');
        [$first] = $this->terms;
        $pupil = $this->pupil();
        $this->grade($pupil, $first, $this->math, 8);

        $otherClass = SchoolClass::factory()->create(['academic_year' => '2025-2026']);
        $star = Student::factory()->create(['school_class_id' => $otherClass->id]);
        $this->grade($star, $first, $this->math, 20);

        $results = $this->actingAs($admin)->getJson("/api/students/{$pupil->id}/results?academic_year=2025-2026")->json('data');
        $term = $this->period($results, $first->id);

        $this->assertSame(1, $term['rank']);
        $this->assertSame(1, $term['ranked_count']);
        $this->assertSame($this->class->id, $results['school_class']['id']);
    }

    #[Test]
    public function un_eleve_sans_inscription_pour_l_annee_a_des_moyennes_mais_pas_de_rang(): void
    {
        $admin = $this->userWithRole('admin');
        [$first] = $this->terms;
        $pupil = Student::factory()->create();
        $this->grade($pupil, $first, $this->math, 11);

        $results = $this->actingAs($admin)->getJson("/api/students/{$pupil->id}/results?academic_year=2025-2026")
            ->assertOk()
            ->json('data');

        $this->assertNull($results['school_class']);
        $this->assertSame(11.0, (float) $this->period($results, $first->id)['average']);
        $this->assertNull($this->period($results, $first->id)['rank']);
    }

    #[Test]
    public function les_decisions_de_passage_sont_suggerees_sur_l_annee_seulement(): void
    {
        $admin = $this->userWithRole('admin');
        [$first] = $this->terms;
        $passing = $this->pupil('Awa');
        $failing = $this->pupil('Bakary');
        $this->grade($passing, $first, $this->math, 14);
        $this->grade($failing, $first, $this->math, 8);

        $termRows = $this->actingAs($admin)
            ->getJson("/api/results?school_class_id={$this->class->id}&period=term&term_id={$first->id}")
            ->json('data.rows');
        $this->assertNull($termRows[0]['suggested_decision']);

        $annual = $this->actingAs($admin)
            ->getJson("/api/results?school_class_id={$this->class->id}&period=annual")
            ->assertOk()
            ->assertJsonPath('data.pass_mark', 10)
            ->assertJsonPath('data.rows.0.suggested_decision.value', 'admitted')
            ->assertJsonPath('data.rows.1.suggested_decision.value', 'repeat')
            ->assertJsonPath('data.rows.0.decision', null);

        $this->assertSame(14.0, (float) $annual->json('data.rows.0.average'));
    }

    #[Test]
    public function la_validation_de_classe_enregistre_les_decisions_suggerees_sans_ecraser_les_corrections(): void
    {
        $admin = $this->userWithRole('admin');
        [$first] = $this->terms;
        $passing = $this->pupil('Awa');
        $failing = $this->pupil('Bakary');
        $ungraded = $this->pupil('Cissé');
        $this->grade($passing, $first, $this->math, 14);
        $this->grade($failing, $first, $this->math, 8);

        // L'administration corrige a la main la decision du deuxieme eleve avant la validation.
        $failingEnrollment = Enrollment::query()->where('student_id', $failing->id)->firstOrFail();
        $this->actingAs($admin)
            ->putJson("/api/enrollments/{$failingEnrollment->id}/decision", ['decision' => 'admitted', 'note' => 'Rattrapage réussi'])
            ->assertOk()
            ->assertJsonPath('data.value', 'admitted')
            ->assertJsonPath('data.average', 8);

        $this->actingAs($admin)
            ->postJson("/api/classes/{$this->class->id}/decisions/validate")
            ->assertOk()
            ->assertJsonPath('data.validated', 1);

        $rows = collect($this->actingAs($admin)->getJson("/api/results?school_class_id={$this->class->id}&period=annual")->json('data.rows'))
            ->keyBy('student.id');

        $this->assertSame('admitted', $rows[$passing->id]['decision']['value']);
        $this->assertSame('admitted', $rows[$failing->id]['decision']['value']);
        $this->assertSame('Rattrapage réussi', $rows[$failing->id]['decision']['note']);
        $this->assertNull($rows[$ungraded->id]['decision']);
    }

    #[Test]
    public function une_decision_peut_etre_remplacee_et_reste_unique_par_inscription(): void
    {
        $admin = $this->userWithRole('admin');
        $pupil = $this->pupil();
        $enrollment = Enrollment::query()->where('student_id', $pupil->id)->firstOrFail();

        $this->actingAs($admin)->putJson("/api/enrollments/{$enrollment->id}/decision", ['decision' => 'repeat'])->assertOk();
        $this->actingAs($admin)->putJson("/api/enrollments/{$enrollment->id}/decision", ['decision' => 'excluded'])->assertOk();

        $this->assertDatabaseCount('promotion_decisions', 1);
        $this->assertDatabaseHas('promotion_decisions', ['enrollment_id' => $enrollment->id, 'decision' => 'excluded']);
        $this->actingAs($admin)->putJson("/api/enrollments/{$enrollment->id}/decision", ['decision' => 'promoted'])->assertStatus(422);
    }

    #[Test]
    public function un_semestre_sans_assez_de_trimestres_est_refuse_avec_une_erreur_explicite(): void
    {
        $admin = $this->userWithRole('admin');
        $class = SchoolClass::factory()->create(['academic_year' => '2030-2031']);
        Term::factory()->create(['name' => '1er trimestre', 'academic_year' => '2030-2031', 'starts_at' => '2030-10-01', 'ends_at' => '2030-12-31']);

        $this->actingAs($admin)
            ->getJson("/api/results?school_class_id={$class->id}&period=semester&semester=1")
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'results_incomplete_calendar');
    }

    #[Test]
    public function un_trimestre_d_une_autre_annee_est_refuse(): void
    {
        $admin = $this->userWithRole('admin');
        $foreign = Term::factory()->create(['name' => '1er trimestre', 'academic_year' => '2031-2032', 'starts_at' => '2031-10-01', 'ends_at' => '2031-12-31']);

        $this->actingAs($admin)
            ->getJson("/api/results?school_class_id={$this->class->id}&period=term&term_id={$foreign->id}")
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'results_term_outside_year');
    }

    #[Test]
    public function la_periode_exige_ses_parametres(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->getJson("/api/results?school_class_id={$this->class->id}&period=term")->assertStatus(422)->assertJsonValidationErrors('term_id');
        $this->actingAs($admin)->getJson("/api/results?school_class_id={$this->class->id}&period=semester")->assertStatus(422)->assertJsonValidationErrors('semester');
        $this->actingAs($admin)->getJson('/api/results')->assertStatus(422)->assertJsonValidationErrors('school_class_id');
    }

    #[Test]
    public function l_enseignant_consulte_les_resultats_mais_ne_decide_pas_du_passage(): void
    {
        $teacher = $this->userWithRole('teacher');
        $pupil = $this->pupil();
        $enrollment = Enrollment::query()->where('student_id', $pupil->id)->firstOrFail();

        $this->actingAs($teacher)->getJson("/api/results?school_class_id={$this->class->id}&period=annual")->assertOk();
        $this->actingAs($teacher)->putJson("/api/enrollments/{$enrollment->id}/decision", ['decision' => 'admitted'])->assertForbidden();
        $this->actingAs($teacher)->postJson("/api/classes/{$this->class->id}/decisions/validate")->assertForbidden();
    }

    #[Test]
    public function le_comptable_n_a_pas_acces_aux_resultats(): void
    {
        $accountant = $this->userWithRole('accountant');

        $this->actingAs($accountant)->getJson("/api/results?school_class_id={$this->class->id}&period=annual")->assertForbidden();
    }

    #[Test]
    public function le_parent_consulte_les_resultats_de_son_enfant_uniquement(): void
    {
        [$first] = $this->terms;
        $child = $this->pupil('Awa');
        $other = $this->pupil('Bakary');
        $this->grade($child, $first, $this->math, 13);
        $this->grade($other, $first, $this->math, 19);

        $this->actingAs($child)->getJson('/api/parent/results?academic_year=2025-2026')
            ->assertOk()
            ->assertJsonPath('data.student.id', $child->id)
            ->assertJsonPath('data.periods.0.rank', 2)
            ->assertJsonPath('data.periods.0.average', 13);

        // Ni le classement d'une classe ni le resultat d'un autre eleve ne sont accessibles au parent.
        $this->actingAs($child)->getJson("/api/results?school_class_id={$this->class->id}&period=annual")->assertForbidden();
        $this->actingAs($child)->getJson("/api/students/{$other->id}/results")->assertForbidden();
    }
}
