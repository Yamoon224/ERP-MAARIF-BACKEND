<?php

namespace Tests\Feature\Accounting;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TuitionInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsSchoolYear;
use Tests\TestCase;

/**
 * Scolarite mensuelle, payable par mois, trimestre, semestre ou annee.
 * L'annee de test compte neuf mois (octobre a juin) a 50 000 par mois.
 */
class TuitionPaymentTest extends TestCase
{
    use BuildsSchoolYear;
    use RefreshDatabase;

    private const FEE = 50000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-01-15 10:00:00');
    }

    /** @return array{class: SchoolClass, student: Student, enrollment: Enrollment} */
    private function enrolledStudent(): array
    {
        $class = $this->schoolYear('2025-2026', self::FEE)['class'];
        $student = Student::factory()->create(['school_class_id' => $class->id]);

        return [
            'class' => $class,
            'student' => $student,
            'enrollment' => Enrollment::where('student_id', $student->id)->firstOrFail(),
        ];
    }

    /** @return array<string, mixed> */
    private function pay(Enrollment $enrollment, string $period, array $extra = []): array
    {
        return ['enrollment_id' => $enrollment->id, 'period' => $period, 'method' => 'cash', ...$extra];
    }

    #[Test]
    public function l_inscription_genere_un_echeancier_de_neuf_mois_a_la_scolarite_de_la_classe(): void
    {
        ['enrollment' => $enrollment] = $this->enrolledStudent();
        $accountant = $this->userWithRole('accountant');

        $response = $this->actingAs($accountant)->getJson("/api/enrollments/{$enrollment->id}/tuition")->assertOk();

        $response->assertJsonCount(9, 'data.installments')
            ->assertJsonPath('data.installments.0.month', '2025-10')
            ->assertJsonPath('data.installments.8.month', '2026-06')
            ->assertJsonPath('data.totals.total', 450000)
            ->assertJsonPath('data.totals.paid', 0)
            ->assertJsonPath('data.totals.remaining', 450000);
    }

    #[Test]
    public function les_mois_passes_sont_en_retard_le_mois_courant_est_a_payer_les_suivants_a_venir(): void
    {
        ['enrollment' => $enrollment] = $this->enrolledStudent();
        $accountant = $this->userWithRole('accountant');

        $response = $this->actingAs($accountant)->getJson("/api/enrollments/{$enrollment->id}/tuition")->assertOk();

        $statuses = collect($response->json('data.installments'))->pluck('status', 'month');
        $this->assertSame('overdue', $statuses['2025-12']);
        $this->assertSame('due', $statuses['2026-01']);
        $this->assertSame('upcoming', $statuses['2026-02']);
        $response->assertJsonPath('data.totals.overdue_months', 3)->assertJsonPath('data.totals.overdue_amount', 150000);
    }

    #[Test]
    public function un_paiement_mensuel_regle_le_plus_ancien_mois_impaye_et_delivre_un_recu(): void
    {
        ['enrollment' => $enrollment] = $this->enrolledStudent();
        $accountant = $this->userWithRole('accountant');

        $this->actingAs($accountant)->postJson('/api/payments', $this->pay($enrollment, 'monthly', ['reference' => 'OM-123']))
            ->assertCreated()
            ->assertJsonPath('data.receipt_number', 'REC-2026-000001')
            ->assertJsonPath('data.period_type', 'monthly')
            ->assertJsonPath('data.months', ['2025-10'])
            ->assertJsonPath('data.amount', 50000)
            ->assertJsonPath('data.status', 'valid')
            ->assertJsonPath('data.received_by.id', $accountant->id);

        $this->assertSame(1, TuitionInstallment::whereNotNull('payment_id')->count());
    }

    /** @return array<string, array{string, int}> */
    public static function formules(): array
    {
        return [
            'mensuel' => ['monthly', 1],
            'trimestre' => ['quarterly', 3],
            'semestre' => ['semiannual', 6],
            'annee scolaire' => ['annual', 9],
        ];
    }

    #[Test]
    #[DataProvider('formules')]
    public function chaque_formule_regle_le_bon_nombre_de_mois(string $period, int $months): void
    {
        ['enrollment' => $enrollment] = $this->enrolledStudent();
        $accountant = $this->userWithRole('accountant');

        $this->actingAs($accountant)->postJson('/api/payments', $this->pay($enrollment, $period))
            ->assertCreated()
            ->assertJsonCount($months, 'data.months')
            ->assertJsonPath('data.months_count', $months)
            ->assertJsonPath('data.amount', $months * self::FEE);
    }

    #[Test]
    public function les_paiements_successifs_avancent_mois_apres_mois_sans_chevauchement(): void
    {
        ['enrollment' => $enrollment] = $this->enrolledStudent();
        $accountant = $this->userWithRole('accountant');

        $this->actingAs($accountant)->postJson('/api/payments', $this->pay($enrollment, 'monthly'))->assertCreated();
        $this->actingAs($accountant)->postJson('/api/payments', $this->pay($enrollment, 'quarterly'))
            ->assertCreated()
            ->assertJsonPath('data.months', ['2025-11', '2025-12', '2026-01']);

        $this->actingAs($accountant)->getJson("/api/enrollments/{$enrollment->id}/tuition")
            ->assertJsonPath('data.totals.months_paid', 4)
            ->assertJsonPath('data.totals.overdue_months', 0);
    }

    #[Test]
    public function la_formule_annuelle_regle_tous_les_mois_restants(): void
    {
        ['enrollment' => $enrollment] = $this->enrolledStudent();
        $accountant = $this->userWithRole('accountant');

        $this->actingAs($accountant)->postJson('/api/payments', $this->pay($enrollment, 'monthly'))->assertCreated();
        $this->actingAs($accountant)->postJson('/api/payments', $this->pay($enrollment, 'annual'))
            ->assertCreated()
            ->assertJsonCount(8, 'data.months')
            ->assertJsonPath('data.amount', 8 * self::FEE);

        $this->actingAs($accountant)->getJson("/api/enrollments/{$enrollment->id}/tuition")
            ->assertJsonPath('data.totals.remaining', 0);
    }

    #[Test]
    public function une_formule_plus_longue_que_les_mois_restants_regle_seulement_ce_qui_reste(): void
    {
        ['enrollment' => $enrollment] = $this->enrolledStudent();
        $accountant = $this->userWithRole('accountant');

        $this->actingAs($accountant)->postJson('/api/payments', $this->pay($enrollment, 'semiannual'))->assertCreated();
        $this->actingAs($accountant)->postJson('/api/payments', $this->pay($enrollment, 'semiannual'))
            ->assertCreated()
            ->assertJsonCount(3, 'data.months');
    }

    #[Test]
    public function payer_quand_tout_est_regle_est_refuse(): void
    {
        ['enrollment' => $enrollment] = $this->enrolledStudent();
        $accountant = $this->userWithRole('accountant');

        $this->actingAs($accountant)->postJson('/api/payments', $this->pay($enrollment, 'annual'))->assertCreated();

        $this->actingAs($accountant)->postJson('/api/payments', $this->pay($enrollment, 'monthly'))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'nothing_to_pay');

        $this->assertSame(1, Payment::count());
    }

    #[Test]
    public function sans_tarif_defini_aucun_paiement_n_est_possible(): void
    {
        ['class' => $class, 'enrollment' => $enrollment] = $this->enrolledStudent();
        $class->update(['monthly_fee' => 0]);
        TuitionInstallment::query()->delete();
        $accountant = $this->userWithRole('accountant');

        $this->actingAs($accountant)->postJson('/api/payments', $this->pay($enrollment, 'monthly'))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'nothing_to_pay');
    }

    #[Test]
    public function le_montant_est_calcule_par_le_serveur_et_jamais_pris_de_la_requete(): void
    {
        ['enrollment' => $enrollment] = $this->enrolledStudent();
        $accountant = $this->userWithRole('accountant');

        $this->actingAs($accountant)->postJson('/api/payments', $this->pay($enrollment, 'monthly', ['amount' => 1]))
            ->assertCreated()
            ->assertJsonPath('data.amount', 50000);
    }

    #[Test]
    public function l_apercu_montre_les_mois_et_le_montant_sans_rien_enregistrer(): void
    {
        ['enrollment' => $enrollment] = $this->enrolledStudent();
        $accountant = $this->userWithRole('accountant');

        $this->actingAs($accountant)->getJson("/api/enrollments/{$enrollment->id}/payment-preview?period=quarterly")
            ->assertOk()
            ->assertJsonPath('data.months', ['2025-10', '2025-11', '2025-12'])
            ->assertJsonPath('data.requested_months', 3)
            ->assertJsonPath('data.amount', 150000);

        $this->assertSame(0, Payment::count());
    }

    #[Test]
    public function une_formule_inconnue_est_refusee(): void
    {
        ['enrollment' => $enrollment] = $this->enrolledStudent();
        $accountant = $this->userWithRole('accountant');

        $this->actingAs($accountant)->postJson('/api/payments', $this->pay($enrollment, 'biannuel'))
            ->assertStatus(422)->assertJsonValidationErrors('period');
    }

    #[Test]
    public function annuler_un_paiement_libere_ses_mois_mais_garde_le_recu(): void
    {
        ['enrollment' => $enrollment] = $this->enrolledStudent();
        $accountant = $this->userWithRole('accountant');

        $id = $this->actingAs($accountant)->postJson('/api/payments', $this->pay($enrollment, 'quarterly'))->json('data.id');

        $this->actingAs($accountant)->postJson("/api/payments/{$id}/cancel", ['reason' => 'Erreur de saisie'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Erreur de saisie')
            ->assertJsonPath('data.months', ['2025-10', '2025-11', '2025-12']);

        $this->assertSame(0, TuitionInstallment::whereNotNull('payment_id')->count());

        // Les mois liberes peuvent etre payes a nouveau, sous un nouveau numero de recu.
        $this->actingAs($accountant)->postJson('/api/payments', $this->pay($enrollment, 'monthly'))
            ->assertCreated()
            ->assertJsonPath('data.receipt_number', 'REC-2026-000002');
    }

    #[Test]
    public function un_paiement_ne_s_annule_qu_une_fois_et_avec_un_motif(): void
    {
        ['enrollment' => $enrollment] = $this->enrolledStudent();
        $accountant = $this->userWithRole('accountant');
        $id = $this->actingAs($accountant)->postJson('/api/payments', $this->pay($enrollment, 'monthly'))->json('data.id');

        $this->actingAs($accountant)->postJson("/api/payments/{$id}/cancel", [])
            ->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->actingAs($accountant)->postJson("/api/payments/{$id}/cancel", ['reason' => 'Doublon'])->assertOk();
        $this->actingAs($accountant)->postJson("/api/payments/{$id}/cancel", ['reason' => 'Doublon'])
            ->assertStatus(409)->assertJsonPath('error_code', 'payment_already_cancelled');
    }

    #[Test]
    public function un_nouveau_tarif_s_applique_aux_mois_non_regles_uniquement(): void
    {
        ['class' => $class, 'enrollment' => $enrollment] = $this->enrolledStudent();
        $accountant = $this->userWithRole('accountant');
        $this->actingAs($accountant)->postJson('/api/payments', $this->pay($enrollment, 'monthly'))->assertCreated();

        $this->actingAs($accountant)->putJson("/api/classes/{$class->id}/fee", ['monthly_fee' => 60000])
            ->assertOk()->assertJsonPath('data.monthly_fee', 60000);

        $response = $this->actingAs($accountant)->getJson("/api/enrollments/{$enrollment->id}/tuition")->assertOk();
        $amounts = collect($response->json('data.installments'))->pluck('amount', 'month');
        $this->assertEquals(50000, $amounts['2025-10']); // deja paye, montant inchange
        $this->assertEquals(60000, $amounts['2025-11']);
        $response->assertJsonPath('data.totals.total', 50000 + 8 * 60000);
    }

    #[Test]
    public function fixer_le_tarif_d_une_classe_cree_les_echeances_de_tous_ses_eleves(): void
    {
        $class = $this->schoolYear('2025-2026', 0)['class'];
        $students = Student::factory()->count(2)->create(['school_class_id' => $class->id]);
        $accountant = $this->userWithRole('accountant');
        $this->assertSame(0, TuitionInstallment::count());

        $this->actingAs($accountant)->putJson("/api/classes/{$class->id}/fee", ['monthly_fee' => 40000])->assertOk();

        $this->assertSame(2 * 9, TuitionInstallment::count());
        $this->assertSame(2, Enrollment::whereIn('student_id', $students->pluck('id'))->count());
    }

    #[Test]
    public function le_tarif_ne_peut_pas_etre_negatif(): void
    {
        ['class' => $class] = $this->enrolledStudent();
        $accountant = $this->userWithRole('accountant');

        $this->actingAs($accountant)->putJson("/api/classes/{$class->id}/fee", ['monthly_fee' => -1])
            ->assertStatus(422)->assertJsonValidationErrors('monthly_fee');
    }

    #[Test]
    public function un_enseignant_ne_peut_ni_encaisser_ni_consulter_la_comptabilite(): void
    {
        ['enrollment' => $enrollment] = $this->enrolledStudent();
        $teacher = $this->userWithRole('teacher');

        $this->actingAs($teacher)->postJson('/api/payments', $this->pay($enrollment, 'monthly'))->assertForbidden();
        $this->actingAs($teacher)->getJson('/api/payments')->assertForbidden();
        $this->actingAs($teacher)->getJson("/api/enrollments/{$enrollment->id}/tuition")->assertForbidden();
        $this->actingAs($teacher)->putJson("/api/classes/{$enrollment->school_class_id}/fee", ['monthly_fee' => 1])->assertForbidden();
    }

    #[Test]
    public function la_liste_des_paiements_se_filtre_par_periode_mode_recherche_et_statut(): void
    {
        ['enrollment' => $enrollment, 'student' => $student] = $this->enrolledStudent();
        $accountant = $this->userWithRole('accountant');

        $this->actingAs($accountant)->postJson('/api/payments', $this->pay($enrollment, 'monthly', ['paid_at' => '2025-10-05']))->assertCreated();
        $this->actingAs($accountant)->postJson('/api/payments', $this->pay($enrollment, 'monthly', ['paid_at' => '2025-11-05', 'method' => 'mobile_money']))->assertCreated();
        $second = Payment::orderBy('paid_at', 'desc')->first();

        $this->actingAs($accountant)->getJson('/api/payments?month=2025-11')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second->id);

        $this->actingAs($accountant)->getJson('/api/payments?method=cash')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($accountant)->getJson('/api/payments?academic_year=2025-2026')
            ->assertOk()->assertJsonCount(2, 'data');

        $this->actingAs($accountant)->getJson('/api/payments?search='.$student->matricule)
            ->assertOk()->assertJsonCount(2, 'data');

        $this->actingAs($accountant)->getJson('/api/payments?search=inconnu')
            ->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($accountant)->postJson("/api/payments/{$second->id}/cancel", ['reason' => 'Doublon'])->assertOk();
        $this->actingAs($accountant)->getJson('/api/payments?status=cancelled')
            ->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($accountant)->getJson('/api/payments?status=valid')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function le_recu_d_un_paiement_est_consultable(): void
    {
        ['enrollment' => $enrollment, 'student' => $student] = $this->enrolledStudent();
        $accountant = $this->userWithRole('accountant');
        $id = $this->actingAs($accountant)->postJson('/api/payments', $this->pay($enrollment, 'quarterly'))->json('data.id');

        $this->actingAs($accountant)->getJson("/api/payments/{$id}")
            ->assertOk()
            ->assertJsonPath('data.student.matricule', $student->matricule)
            ->assertJsonPath('data.enrollment.school_class.name', '6eme A')
            ->assertJsonPath('data.enrollment.academic_year', '2025-2026');
    }

    #[Test]
    public function le_parent_consulte_la_scolarite_de_son_enfant_uniquement(): void
    {
        ['student' => $student, 'enrollment' => $enrollment] = $this->enrolledStudent();
        $otherClass = $this->schoolYear('2026-2027', 60000, '5eme A')['class'];
        Student::factory()->create(['school_class_id' => $otherClass->id]);
        $accountant = $this->userWithRole('accountant');
        $this->actingAs($accountant)->postJson('/api/payments', $this->pay($enrollment, 'quarterly'))->assertCreated();

        $response = $this->actingAs($student)->getJson('/api/parent/tuition')->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.enrollment.student.id', $student->id)
            ->assertJsonPath('data.0.totals.months_paid', 3);

        $this->actingAs($student)->getJson('/api/parent/payments')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.student.id', $student->id);
    }

    #[Test]
    public function le_parent_ne_peut_ni_encaisser_ni_lire_la_comptabilite_du_personnel(): void
    {
        ['student' => $student, 'enrollment' => $enrollment] = $this->enrolledStudent();

        $this->actingAs($student)->postJson('/api/payments', $this->pay($enrollment, 'monthly'))->assertForbidden();
        $this->actingAs($student)->getJson('/api/payments')->assertForbidden();
        $this->actingAs($student)->getJson('/api/accounting/summary')->assertForbidden();
    }

    #[Test]
    public function un_parent_ne_voit_pas_les_paiements_annules(): void
    {
        ['student' => $student, 'enrollment' => $enrollment] = $this->enrolledStudent();
        $accountant = $this->userWithRole('accountant');
        $id = $this->actingAs($accountant)->postJson('/api/payments', $this->pay($enrollment, 'monthly'))->json('data.id');
        $this->actingAs($accountant)->postJson("/api/payments/{$id}/cancel", ['reason' => 'Erreur'])->assertOk();

        $this->actingAs($student)->getJson('/api/parent/payments')->assertOk()->assertJsonCount(0, 'data');
    }

    #[Test]
    public function une_annee_sans_trimestre_n_a_pas_d_echeancier(): void
    {
        $class = SchoolClass::factory()->create(['academic_year' => '2030-2031', 'monthly_fee' => 50000]);
        $student = Student::factory()->create(['school_class_id' => $class->id]);
        $enrollment = Enrollment::where('student_id', $student->id)->firstOrFail();
        $accountant = $this->userWithRole('accountant');

        $this->actingAs($accountant)->getJson("/api/enrollments/{$enrollment->id}/tuition")
            ->assertOk()->assertJsonCount(0, 'data.installments');

        $this->actingAs($accountant)->postJson('/api/payments', $this->pay($enrollment, 'monthly'))
            ->assertStatus(422)->assertJsonPath('error_code', 'nothing_to_pay');
    }
}
