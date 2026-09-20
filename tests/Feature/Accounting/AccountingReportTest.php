<?php

namespace Tests\Feature\Accounting;

use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsSchoolYear;
use Tests\TestCase;

/**
 * Tableau de bord comptable, a "aujourd'hui" = 15 janvier 2026 : octobre a
 * decembre sont des mois termines, janvier est le mois en cours.
 */
class AccountingReportTest extends TestCase
{
    use BuildsSchoolYear;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-01-15 10:00:00');
    }

    /**
     * Deux eleves de la meme classe (50 000/mois) :
     *  - Aminata paie le premier trimestre (150 000) le 20 octobre ;
     *  - Ibrahima paie octobre (50 000) le 5 novembre puis plus rien.
     *
     * @return array{aminata: Student, ibrahima: Student}
     */
    private function twoStudentsWithPayments(): array
    {
        $class = $this->schoolYear()['class'];
        $accountant = $this->userWithRole('accountant');
        $aminata = Student::factory()->create(['school_class_id' => $class->id, 'first_name' => 'Aminata']);
        $ibrahima = Student::factory()->create(['school_class_id' => $class->id, 'first_name' => 'Ibrahima']);

        foreach ([[$aminata, 'quarterly', '2025-10-20'], [$ibrahima, 'monthly', '2025-11-05']] as [$student, $period, $date]) {
            $this->actingAs($accountant)->postJson('/api/payments', [
                'enrollment_id' => Enrollment::where('student_id', $student->id)->value('id'),
                'period' => $period,
                'method' => 'cash',
                'paid_at' => $date,
            ])->assertCreated();
        }

        return ['aminata' => $aminata, 'ibrahima' => $ibrahima];
    }

    #[Test]
    public function le_resume_totalise_les_encaissements_par_formule_et_par_mode(): void
    {
        $this->twoStudentsWithPayments();
        $accountant = $this->userWithRole('accountant');

        $response = $this->actingAs($accountant)->getJson('/api/accounting/summary?academic_year=2025-2026')->assertOk();

        $response->assertJsonPath('data.collected.total', 200000)
            ->assertJsonPath('data.collected.count', 2);

        $byType = collect($response->json('data.by_period_type'))->keyBy('key');
        $this->assertCount(4, $byType);
        $this->assertEquals(150000, $byType['quarterly']['total']);
        $this->assertEquals(50000, $byType['monthly']['total']);
        $this->assertEquals(0, $byType['annual']['total']);

        $byMethod = collect($response->json('data.by_method'))->keyBy('key');
        $this->assertEquals(200000, $byMethod['cash']['total']);
        $this->assertEquals(0, $byMethod['cheque']['total']);
    }

    #[Test]
    public function une_annee_scolaire_compte_aussi_les_paiements_faits_avant_la_rentree(): void
    {
        $class = $this->schoolYear('2026-2027')['class'];
        $accountant = $this->userWithRole('accountant');
        $student = Student::factory()->create(['school_class_id' => $class->id]);

        // Regle en decembre 2025 pour l'annee 2026-2027, qui ne demarre qu'en octobre 2026 ("aujourd'hui" : 15 janvier 2026).
        $this->actingAs($accountant)->postJson('/api/payments', [
            'enrollment_id' => Enrollment::where('student_id', $student->id)->value('id'),
            'period' => 'annual',
            'method' => 'bank_transfer',
            'paid_at' => '2025-12-20',
        ])->assertCreated();

        // L'annee entiere : le paiement est compte, meme s'il precede le premier trimestre.
        $this->actingAs($accountant)->getJson('/api/accounting/summary?academic_year=2026-2027')
            ->assertJsonPath('data.collected.total', 450000)
            ->assertJsonPath('data.collected.count', 1);
        $this->actingAs($accountant)->getJson('/api/payments?academic_year=2026-2027')
            ->assertJsonCount(1, 'data');

        // Un trimestre ou un mois : c'est la caisse, donc la date du paiement.
        $this->actingAs($accountant)->getJson('/api/accounting/summary?month=2026-10')
            ->assertJsonPath('data.collected.total', 0);
        $this->actingAs($accountant)->getJson('/api/accounting/summary?month=2025-12')
            ->assertJsonPath('data.collected.total', 450000);
    }

    #[Test]
    public function le_filtre_mensuel_ne_garde_que_les_encaissements_du_mois(): void
    {
        $this->twoStudentsWithPayments();
        $accountant = $this->userWithRole('accountant');

        $this->actingAs($accountant)->getJson('/api/accounting/summary?month=2025-11')
            ->assertOk()
            ->assertJsonPath('data.collected.total', 50000)
            ->assertJsonPath('data.collected.count', 1);

        $this->actingAs($accountant)->getJson('/api/accounting/summary?month=2025-10')
            ->assertOk()
            ->assertJsonPath('data.collected.total', 150000);
    }

    #[Test]
    public function l_histogramme_mensuel_couvre_tous_les_mois_de_la_periode(): void
    {
        $this->twoStudentsWithPayments();
        $accountant = $this->userWithRole('accountant');

        $months = collect($this->actingAs($accountant)->getJson('/api/accounting/summary?academic_year=2025-2026')->json('data.by_month'));

        $this->assertCount(9, $months);
        $this->assertEquals(150000, $months->firstWhere('month', '2025-10')['total']);
        $this->assertEquals(50000, $months->firstWhere('month', '2025-11')['total']);
        $this->assertEquals(0, $months->firstWhere('month', '2026-03')['total']);
    }

    #[Test]
    public function le_taux_de_recouvrement_compare_le_regle_au_du_de_la_periode(): void
    {
        $this->twoStudentsWithPayments();
        $accountant = $this->userWithRole('accountant');

        // Octobre : 2 eleves x 50 000 dus, 100 000 regles (Aminata + Ibrahima) => 100 %.
        $this->actingAs($accountant)->getJson('/api/accounting/summary?month=2025-10')
            ->assertJsonPath('data.expected.total', 100000)
            ->assertJsonPath('data.expected.settled', 100000)
            ->assertJsonPath('data.expected.rate', 100);

        // Decembre : 100 000 dus, seule Aminata a paye => 50 %.
        $this->actingAs($accountant)->getJson('/api/accounting/summary?month=2025-12')
            ->assertJsonPath('data.expected.rate', 50);
    }

    #[Test]
    public function les_impayes_ne_comptent_que_les_mois_termines_non_regles(): void
    {
        ['ibrahima' => $ibrahima] = $this->twoStudentsWithPayments();
        $accountant = $this->userWithRole('accountant');

        // Ibrahima : novembre et decembre en retard. Janvier (en cours) n'est pas un impaye.
        $this->actingAs($accountant)->getJson('/api/accounting/summary?academic_year=2025-2026')
            ->assertJsonPath('data.arrears.amount', 100000)
            ->assertJsonPath('data.arrears.students', 1)
            ->assertJsonPath('data.arrears.months', 2);

        $this->actingAs($accountant)->getJson('/api/accounting/arrears')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student.id', $ibrahima->id)
            ->assertJsonPath('data.0.months_overdue', 2)
            ->assertJsonPath('data.0.amount', 100000)
            ->assertJsonPath('data.0.oldest_month', '2025-11')
            ->assertJsonPath('data.0.school_class', '6eme A')
            ->assertJsonPath('meta.total', 1);
    }

    #[Test]
    public function les_impayes_se_recherchent_et_se_filtrent_par_classe(): void
    {
        ['ibrahima' => $ibrahima] = $this->twoStudentsWithPayments();
        $accountant = $this->userWithRole('accountant');

        $this->actingAs($accountant)->getJson('/api/accounting/arrears?search=Ibrahima')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($accountant)->getJson('/api/accounting/arrears?search='.$ibrahima->matricule)->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($accountant)->getJson('/api/accounting/arrears?search=Aminata')->assertOk()->assertJsonCount(0, 'data');

        $otherClass = $this->schoolYear('2026-2027', 60000, '5eme A')['class'];
        $this->actingAs($accountant)->getJson("/api/accounting/arrears?school_class_id={$otherClass->id}")->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($accountant)->getJson('/api/accounting/arrears?school_class_id='.$ibrahima->school_class_id)->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function un_eleve_dont_la_classe_vient_de_recevoir_un_tarif_apparait_deja_dans_les_impayes(): void
    {
        $class = $this->schoolYear('2025-2026', 0)['class'];
        Student::factory()->create(['school_class_id' => $class->id]);
        $accountant = $this->userWithRole('accountant');

        $this->actingAs($accountant)->getJson('/api/accounting/arrears')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($accountant)->putJson("/api/classes/{$class->id}/fee", ['monthly_fee' => 30000])->assertOk();

        $this->actingAs($accountant)->getJson('/api/accounting/arrears')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.months_overdue', 3)
            ->assertJsonPath('data.0.amount', 90000);
    }

    #[Test]
    public function un_paiement_annule_sort_des_encaissements_et_redevient_un_impaye(): void
    {
        ['aminata' => $aminata] = $this->twoStudentsWithPayments();
        $accountant = $this->userWithRole('accountant');
        $paymentId = $this->actingAs($accountant)->getJson('/api/payments?search='.$aminata->matricule)->json('data.0.id');

        $this->actingAs($accountant)->postJson("/api/payments/{$paymentId}/cancel", ['reason' => 'Cheque sans provision'])->assertOk();

        $this->actingAs($accountant)->getJson('/api/accounting/summary?academic_year=2025-2026')
            ->assertJsonPath('data.collected.total', 50000)
            ->assertJsonPath('data.arrears.students', 2);
    }

    #[Test]
    public function un_enseignant_n_a_pas_acces_aux_rapports_comptables(): void
    {
        $teacher = $this->userWithRole('teacher');

        $this->actingAs($teacher)->getJson('/api/accounting/summary')->assertForbidden();
        $this->actingAs($teacher)->getJson('/api/accounting/arrears')->assertForbidden();
    }
}
