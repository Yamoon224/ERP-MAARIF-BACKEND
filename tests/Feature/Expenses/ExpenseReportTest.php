<?php

namespace Tests\Feature\Expenses;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Bilan des depenses : total, par categorie, par mode de paiement, mois par mois. */
class ExpenseReportTest extends TestCase
{
    use RefreshDatabase;

    private function spend(ExpenseCategory $category, float $amount, string $date, array $overrides = []): Expense
    {
        return Expense::factory()->create([
            'expense_category_id' => $category->id,
            'unit_price' => $amount,
            'amount' => $amount,
            'spent_at' => $date,
            ...$overrides,
        ]);
    }

    #[Test]
    public function le_bilan_repartit_les_depenses_valides_par_categorie_et_par_mode(): void
    {
        $accountant = $this->userWithRole('accountant');
        $chalk = ExpenseCategory::factory()->create(['name' => 'Craies']);
        $books = ExpenseCategory::factory()->create(['name' => 'Registres']);
        ExpenseCategory::factory()->create(['name' => 'Vide']);

        $this->spend($chalk, 100000, '2025-10-05');
        $this->spend($chalk, 50000, '2025-11-10', ['method' => 'mobile_money']);
        $this->spend($books, 300000, '2025-11-12');
        // Une depense annulee n'entre dans aucun total.
        $this->spend($books, 999999, '2025-11-15', ['cancelled_at' => now(), 'cancellation_reason' => 'Doublon']);

        $response = $this->actingAs($accountant)->getJson('/api/expenses/summary?academic_year=2025-2026')->assertOk();

        $response->assertJsonPath('data.total.total', 450000)
            ->assertJsonPath('data.total.count', 3)
            ->assertJsonPath('data.period.from', '2025-09-01')
            ->assertJsonPath('data.period.to', '2026-08-31');

        // Les plus lourdes d'abord ; une categorie sans depense n'apparait pas.
        $this->assertSame(['Registres', 'Craies'], array_column($response->json('data.by_category'), 'name'));
        $this->assertSame([300000, 150000], array_column($response->json('data.by_category'), 'total'));

        // Tous les modes figurent, meme a zero.
        $this->assertSame(['cash', 'mobile_money', 'bank_transfer', 'cheque'], array_column($response->json('data.by_method'), 'key'));
        $this->assertSame([400000, 50000, 0, 0], array_column($response->json('data.by_method'), 'total'));
    }

    #[Test]
    public function l_evolution_mensuelle_montre_chaque_mois_de_la_periode_meme_a_zero(): void
    {
        $accountant = $this->userWithRole('accountant');
        $category = ExpenseCategory::factory()->create();
        $this->spend($category, 80000, '2025-09-20');
        $this->spend($category, 20000, '2025-11-03');
        $this->spend($category, 5000, '2025-11-28');

        $months = $this->actingAs($accountant)->getJson('/api/expenses/summary?academic_year=2025-2026')->json('data.by_month');

        $this->assertCount(12, $months);
        $this->assertSame('2025-09', $months[0]['month']);
        $this->assertSame('2026-08', $months[11]['month']);
        $this->assertSame(80000, $months[0]['total']);
        $this->assertSame(0, $months[1]['total']);
        $this->assertSame(25000, $months[2]['total']);
    }

    #[Test]
    public function le_bilan_suit_le_mois_choisi(): void
    {
        $accountant = $this->userWithRole('accountant');
        $category = ExpenseCategory::factory()->create();
        $this->spend($category, 80000, '2025-09-20');
        $this->spend($category, 25000, '2025-11-03');

        $this->actingAs($accountant)->getJson('/api/expenses/summary?academic_year=2025-2026&month=2025-11')
            ->assertOk()
            ->assertJsonPath('data.total.total', 25000)
            ->assertJsonPath('data.total.count', 1)
            ->assertJsonCount(1, 'data.by_month');
    }

    #[Test]
    public function sans_depense_le_bilan_est_a_zero(): void
    {
        $accountant = $this->userWithRole('accountant');

        $this->actingAs($accountant)->getJson('/api/expenses/summary?academic_year=2025-2026')
            ->assertOk()
            ->assertJsonPath('data.total.total', 0)
            ->assertJsonPath('data.by_category', []);
    }
}
