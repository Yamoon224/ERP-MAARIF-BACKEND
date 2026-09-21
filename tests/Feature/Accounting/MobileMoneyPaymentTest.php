<?php

namespace Tests\Feature\Accounting;

use App\Models\Enrollment;
use App\Models\MobileMoneyTransaction;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TuitionInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsSchoolYear;
use Tests\TestCase;

/** Paiement de la scolarité par les parents via mobile money (passerelle simulée). */
class MobileMoneyPaymentTest extends TestCase
{
    use BuildsSchoolYear;
    use RefreshDatabase;

    private const FEE = 50000;

    private const PHONE = '622123456';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-01-15 10:00:00');
        // Le simulateur confirme tout de suite : pas d'attente dans les tests.
        config(['mobile_money.sandbox_delay_seconds' => 0]);
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
    private function request(Enrollment $enrollment, string $period = 'monthly', string $phone = self::PHONE): array
    {
        return ['enrollment_id' => $enrollment->id, 'period' => $period, 'operator' => 'orange_money', 'phone' => $phone];
    }

    #[Test]
    public function un_parent_lance_un_paiement_le_montant_et_les_mois_sont_calcules_par_le_serveur(): void
    {
        ['student' => $student, 'enrollment' => $enrollment] = $this->enrolledStudent();

        $this->actingAs($student)->postJson('/api/parent/mobile-money', $this->request($enrollment, 'quarterly'))
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.operator_label', 'Orange Money')
            ->assertJsonPath('data.amount', 150000)
            ->assertJsonPath('data.months', ['2025-10', '2025-11', '2025-12']);

        // Rien n'est réglé tant que l'opérateur n'a pas confirmé.
        $this->assertSame(0, Payment::count());
        $this->assertSame(9, TuitionInstallment::whereNull('payment_id')->count());
    }

    #[Test]
    public function la_confirmation_de_l_operateur_cree_le_paiement_avec_son_recu_et_regle_les_mois(): void
    {
        ['student' => $student, 'enrollment' => $enrollment] = $this->enrolledStudent();
        $created = $this->actingAs($student)->postJson('/api/parent/mobile-money', $this->request($enrollment, 'monthly'))->json('data');

        $this->actingAs($student)->getJson("/api/parent/mobile-money/{$created['id']}")
            ->assertOk()
            ->assertJsonPath('data.status', 'successful')
            ->assertJsonPath('data.receipt_number', 'REC-2026-000001');

        $payment = Payment::firstOrFail();
        $this->assertSame('mobile_money', $payment->method->value);
        $this->assertSame(['2025-10'], $payment->months);
        $this->assertNull($payment->received_by);
        $this->assertSame(1, TuitionInstallment::where('payment_id', $payment->id)->count());

        $this->actingAs($student)->getJson('/api/parent/payments')
            ->assertOk()
            ->assertJsonPath('data.0.method', 'mobile_money')
            ->assertJsonPath('data.0.receipt_number', 'REC-2026-000001');
    }

    #[Test]
    public function un_refus_de_l_operateur_ne_regle_aucun_mois(): void
    {
        ['student' => $student, 'enrollment' => $enrollment] = $this->enrolledStudent();
        $created = $this->actingAs($student)->postJson('/api/parent/mobile-money', $this->request($enrollment, 'monthly', '622123400'))->json('data');

        $this->actingAs($student)->getJson("/api/parent/mobile-money/{$created['id']}")
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.failure_reason', 'Solde insuffisant sur le compte mobile money.');

        $this->assertSame(0, Payment::count());
    }

    #[Test]
    public function une_demande_jamais_validee_expire_puis_une_nouvelle_est_possible(): void
    {
        ['student' => $student, 'enrollment' => $enrollment] = $this->enrolledStudent();
        $created = $this->actingAs($student)->postJson('/api/parent/mobile-money', $this->request($enrollment, 'monthly', '622123499'))->json('data');

        $this->actingAs($student)->getJson("/api/parent/mobile-money/{$created['id']}")->assertJsonPath('data.status', 'pending');

        // Une seule demande en attente à la fois : évite de débiter deux fois les mêmes mois.
        $this->actingAs($student)->postJson('/api/parent/mobile-money', $this->request($enrollment))
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'mobile_money_already_pending');

        $this->travel(16)->minutes();

        $this->actingAs($student)->getJson("/api/parent/mobile-money/{$created['id']}")->assertJsonPath('data.status', 'expired');
        $this->actingAs($student)->postJson('/api/parent/mobile-money', $this->request($enrollment))->assertCreated();
    }

    #[Test]
    public function un_parent_ne_paie_que_l_inscription_de_son_enfant(): void
    {
        ['student' => $student] = $this->enrolledStudent();
        $other = Student::factory()->create(['school_class_id' => SchoolClass::factory()->create(['monthly_fee' => self::FEE])->id]);
        $foreign = Enrollment::where('student_id', $other->id)->firstOrFail();

        $this->actingAs($student)->postJson('/api/parent/mobile-money', $this->request($foreign))->assertNotFound();
        $this->actingAs($student)->getJson("/api/parent/tuition/preview?enrollment_id={$foreign->id}&period=monthly")->assertNotFound();
    }

    #[Test]
    public function un_parent_ne_voit_pas_la_demande_d_un_autre_eleve(): void
    {
        ['student' => $student, 'enrollment' => $enrollment] = $this->enrolledStudent();
        $created = $this->actingAs($student)->postJson('/api/parent/mobile-money', $this->request($enrollment))->json('data');
        $other = Student::factory()->create();

        $this->actingAs($other)->getJson("/api/parent/mobile-money/{$created['id']}")->assertNotFound();
        $this->actingAs($other)->getJson('/api/parent/mobile-money')->assertOk()->assertJsonCount(0, 'data');
    }

    #[Test]
    public function des_mois_regles_entre_temps_par_la_comptabilite_ne_creent_pas_de_second_paiement(): void
    {
        ['student' => $student, 'enrollment' => $enrollment] = $this->enrolledStudent();
        $accountant = $this->userWithRole('accountant');
        $created = $this->actingAs($student)->postJson('/api/parent/mobile-money', $this->request($enrollment, 'monthly'))->json('data');

        // Pendant que le parent valide sur son téléphone, la comptabilité encaisse le même mois en espèces.
        $this->actingAs($accountant)->postJson('/api/payments', ['enrollment_id' => $enrollment->id, 'period' => 'monthly', 'method' => 'cash'])->assertCreated();

        $this->actingAs($student)->getJson("/api/parent/mobile-money/{$created['id']}")
            ->assertOk()
            ->assertJsonPath('data.status', 'needs_review');

        $this->assertSame(1, Payment::count());
        $this->assertSame('cash', Payment::firstOrFail()->method->value);
    }

    #[Test]
    public function un_changement_de_tarif_avant_la_confirmation_est_signale_a_la_comptabilite(): void
    {
        ['student' => $student, 'enrollment' => $enrollment, 'class' => $class] = $this->enrolledStudent();
        $created = $this->actingAs($student)->postJson('/api/parent/mobile-money', $this->request($enrollment, 'monthly'))->json('data');

        $class->update(['monthly_fee' => 60000]);

        $this->actingAs($student)->getJson("/api/parent/mobile-money/{$created['id']}")->assertJsonPath('data.status', 'needs_review');
        $this->assertSame(0, Payment::count());
    }

    #[Test]
    public function le_numero_est_normalise_et_controle(): void
    {
        ['student' => $student, 'enrollment' => $enrollment] = $this->enrolledStudent();

        $this->actingAs($student)->postJson('/api/parent/mobile-money', $this->request($enrollment, 'monthly', '+224 622 12 34 56'))
            ->assertCreated()
            ->assertJsonPath('data.phone', '+224622123456');

        $this->actingAs($student)->postJson('/api/parent/mobile-money', $this->request($enrollment, 'monthly', 'abc'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');
        $this->actingAs($student)->postJson('/api/parent/mobile-money', [...$this->request($enrollment), 'operator' => 'paypal'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('operator');
    }

    #[Test]
    public function rien_a_payer_est_refuse(): void
    {
        ['student' => $student, 'enrollment' => $enrollment] = $this->enrolledStudent();
        $this->actingAs($this->userWithRole('accountant'))->postJson('/api/payments', ['enrollment_id' => $enrollment->id, 'period' => 'annual', 'method' => 'cash'])->assertCreated();

        $this->actingAs($student)->postJson('/api/parent/mobile-money', $this->request($enrollment))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'nothing_to_pay');
    }

    #[Test]
    public function la_comptabilite_suit_les_paiements_mobile_money_et_les_autres_roles_n_y_ont_pas_acces(): void
    {
        ['student' => $student, 'enrollment' => $enrollment] = $this->enrolledStudent();
        $this->actingAs($student)->postJson('/api/parent/mobile-money', $this->request($enrollment))->assertCreated();

        $this->actingAs($this->userWithRole('accountant'))->getJson('/api/mobile-money-transactions?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student.matricule', $student->matricule);

        $this->actingAs($this->userWithRole('teacher'))->getJson('/api/mobile-money-transactions')->assertForbidden();
        $this->actingAs($student)->getJson('/api/mobile-money-transactions')->assertForbidden();
    }

    #[Test]
    public function la_tache_planifiee_impute_les_paiements_confirmes_sans_que_le_parent_revienne(): void
    {
        ['student' => $student, 'enrollment' => $enrollment] = $this->enrolledStudent();
        $this->actingAs($student)->postJson('/api/parent/mobile-money', $this->request($enrollment))->assertCreated();

        Artisan::call('mobile-money:reconcile');

        $this->assertSame('successful', MobileMoneyTransaction::firstOrFail()->status->value);
        $this->assertSame(1, Payment::count());
    }
}
