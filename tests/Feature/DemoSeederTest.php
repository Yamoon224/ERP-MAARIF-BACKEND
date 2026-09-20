<?php

namespace Tests\Feature;

use App\Domains\Admissions\Enums\AdmissionStatus;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Enums\NotificationType;
use App\Domains\Results\Enums\PromotionDecisionType;
use App\Models\AdmissionApplication;
use App\Models\Enrollment;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\PromotionDecision;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Le jeu de demonstration est le point d'entree documente : il doit toujours se charger. */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function le_jeu_de_demonstration_se_charge_avec_deux_annees_et_de_la_comptabilite(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(6, Term::count());
        $this->assertSame(1, Term::where('is_current', true)->count());
        $this->assertTrue(User::where('email', 'comptable@maarif.test')->firstOrFail()->hasRole('accountant'));

        // 10 eleves inscrits deux annees de suite + 5 nouveaux la derniere + 1 candidat admis puis inscrit.
        $this->assertSame(26, Enrollment::count());
        $this->assertGreaterThan(0, Payment::count());

        // Chaque eleve de l'annee passee a une decision, et tous sont reinscrits (admis ou redoublants).
        $this->assertSame(10, PromotionDecision::count());
        $this->assertSame(0, PromotionDecision::where('decision', PromotionDecisionType::Excluded)->count());
        $this->assertSame(16, Enrollment::where('academic_year', Term::where('is_current', true)->value('academic_year'))->count());

        // Sept candidatures, une par etape du parcours, dont une deja inscrite.
        $this->assertSame(7, AdmissionApplication::count());
        $this->assertSame(1, AdmissionApplication::where('status', AdmissionStatus::Enrolled)->whereNotNull('student_id')->count());
        $this->assertSame(
            1,
            AdmissionApplication::where('status', AdmissionStatus::Rejected)->whereNotNull('decision_note')->count(),
        );

        // Le journal des notifications a de quoi montrer : convocations et sanctions envoyees, un echec, et les admissions.
        $this->assertGreaterThan(0, NotificationLog::where('type', NotificationType::Summon)->where('status', NotificationStatus::Sent)->count());
        $this->assertGreaterThan(0, NotificationLog::where('type', NotificationType::Sanction)->count());
        $this->assertSame(1, NotificationLog::where('status', NotificationStatus::Failed)->count());
        // Admis, liste d'attente, refus, puis admis + inscrit pour le candidat inscrit : cinq messages.
        $this->assertSame(5, NotificationLog::where('type', NotificationType::Admission)->count());

        $admin = User::where('email', 'admin@maarif.test')->firstOrFail();
        $this->actingAs($admin)->getJson('/api/notification-logs/summary')->assertOk()->assertJsonPath('data.by_status.failed', 1);
        $this->actingAs($admin)->getJson('/api/dashboard')->assertOk();
        $this->actingAs($admin)->getJson('/api/accounting/summary')->assertOk();
    }
}
