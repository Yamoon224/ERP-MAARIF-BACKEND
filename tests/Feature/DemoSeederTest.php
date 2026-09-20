<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Payment;
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

        // 10 eleves inscrits deux annees de suite + 5 nouveaux la derniere.
        $this->assertSame(25, Enrollment::count());
        $this->assertGreaterThan(0, Payment::count());

        $admin = User::where('email', 'admin@maarif.test')->firstOrFail();
        $this->actingAs($admin)->getJson('/api/dashboard')->assertOk();
        $this->actingAs($admin)->getJson('/api/accounting/summary')->assertOk();
    }
}
