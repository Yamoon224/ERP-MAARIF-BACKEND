<?php

namespace Tests\Feature\Expenses;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Registre des depenses : achat de craies, de registres, factures... */
class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(ExpenseCategory $category, array $overrides = []): array
    {
        return [
            'expense_category_id' => $category->id,
            'label' => 'Craies blanches',
            'supplier_name' => 'Papeterie Centrale',
            'quantity' => 40,
            'unit' => 'boîte',
            'unit_price' => 12000,
            'method' => 'cash',
            'invoice_reference' => 'FAC-2026-118',
            'spent_at' => today()->toDateString(),
            ...$overrides,
        ];
    }

    #[Test]
    public function le_comptable_saisit_une_depense_dont_le_montant_est_calcule_par_le_serveur(): void
    {
        $accountant = $this->userWithRole('accountant');
        $category = ExpenseCategory::factory()->create(['name' => 'Fournitures scolaires']);

        // Un montant envoye par le client est ignore : seul quantite x prix unitaire compte.
        $this->actingAs($accountant)->postJson('/api/expenses', $this->payload($category, ['amount' => 1]))
            ->assertCreated()
            ->assertJsonPath('data.amount', 480000)
            ->assertJsonPath('data.quantity', 40)
            ->assertJsonPath('data.category.name', 'Fournitures scolaires')
            ->assertJsonPath('data.method_label', 'Especes')
            ->assertJsonPath('data.status', 'valid')
            ->assertJsonPath('data.recorded_by.id', $accountant->id);

        $this->assertDatabaseHas('expenses', ['label' => 'Craies blanches', 'amount' => '480000.00']);
    }

    #[Test]
    public function les_numeros_se_suivent_par_annee_et_ne_sont_jamais_reutilises(): void
    {
        $accountant = $this->userWithRole('accountant');
        $category = ExpenseCategory::factory()->create();
        $year = now()->year;

        $first = $this->actingAs($accountant)->postJson('/api/expenses', $this->payload($category))->assertCreated();
        $second = $this->actingAs($accountant)->postJson('/api/expenses', $this->payload($category))->assertCreated();
        $this->actingAs($accountant)->postJson("/api/expenses/{$first->json('data.id')}/cancel", ['reason' => 'Saisie en double'])->assertOk();
        $third = $this->actingAs($accountant)->postJson('/api/expenses', $this->payload($category))->assertCreated();

        $this->assertSame(sprintf('DEP-%d-000001', $year), $first->json('data.number'));
        $this->assertSame(sprintf('DEP-%d-000002', $year), $second->json('data.number'));
        $this->assertSame(sprintf('DEP-%d-000003', $year), $third->json('data.number'));
    }

    #[Test]
    public function la_date_par_defaut_est_aujourd_hui_et_ne_peut_pas_etre_dans_le_futur(): void
    {
        $accountant = $this->userWithRole('accountant');
        $category = ExpenseCategory::factory()->create();

        $this->actingAs($accountant)->postJson('/api/expenses', $this->payload($category, ['spent_at' => null]))
            ->assertCreated()
            ->assertJsonPath('data.spent_at', today()->toDateString());

        $this->actingAs($accountant)->postJson('/api/expenses', $this->payload($category, ['spent_at' => today()->addDay()->toDateString()]))
            ->assertStatus(422)->assertJsonValidationErrors('spent_at');
    }

    #[Test]
    public function la_saisie_est_validee(): void
    {
        $accountant = $this->userWithRole('accountant');
        $category = ExpenseCategory::factory()->create();

        $this->actingAs($accountant)->postJson('/api/expenses', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['expense_category_id', 'label', 'quantity', 'unit_price', 'method']);

        $this->actingAs($accountant)->postJson('/api/expenses', $this->payload($category, ['quantity' => 0]))
            ->assertStatus(422)->assertJsonValidationErrors('quantity');
        $this->actingAs($accountant)->postJson('/api/expenses', $this->payload($category, ['unit_price' => -5]))
            ->assertStatus(422)->assertJsonValidationErrors('unit_price');
        $this->actingAs($accountant)->postJson('/api/expenses', $this->payload($category, ['method' => 'troc']))
            ->assertStatus(422)->assertJsonValidationErrors('method');
    }

    #[Test]
    public function une_categorie_desactivee_n_est_plus_proposee_a_la_saisie(): void
    {
        $accountant = $this->userWithRole('accountant');
        $inactive = ExpenseCategory::factory()->inactive()->create();

        $this->actingAs($accountant)->postJson('/api/expenses', $this->payload($inactive))
            ->assertStatus(422)->assertJsonValidationErrors('expense_category_id');
    }

    #[Test]
    public function une_depense_valide_se_corrige_et_le_montant_est_recalcule(): void
    {
        $accountant = $this->userWithRole('accountant');
        $category = ExpenseCategory::factory()->create();
        $expense = Expense::factory()->create(['expense_category_id' => $category->id, 'number' => 'DEP-2026-000009']);

        $this->actingAs($accountant)->putJson("/api/expenses/{$expense->id}", $this->payload($category, ['quantity' => 3, 'unit_price' => 2500, 'label' => 'Marqueurs']))
            ->assertOk()
            ->assertJsonPath('data.amount', 7500)
            ->assertJsonPath('data.label', 'Marqueurs')
            ->assertJsonPath('data.number', 'DEP-2026-000009');
    }

    #[Test]
    public function une_depense_dont_la_categorie_a_ete_desactivee_reste_modifiable_sans_la_changer(): void
    {
        $accountant = $this->userWithRole('accountant');
        $category = ExpenseCategory::factory()->create();
        $expense = Expense::factory()->create(['expense_category_id' => $category->id]);
        $category->update(['is_active' => false]);

        $this->actingAs($accountant)->putJson("/api/expenses/{$expense->id}", $this->payload($category, ['label' => 'Craies (corrigé)']))
            ->assertOk()->assertJsonPath('data.label', 'Craies (corrigé)');
    }

    #[Test]
    public function une_depense_annulee_garde_son_motif_et_ne_peut_plus_etre_modifiee_ni_reannulee(): void
    {
        $accountant = $this->userWithRole('accountant');
        $category = ExpenseCategory::factory()->create();
        $expense = Expense::factory()->create(['expense_category_id' => $category->id]);

        $this->actingAs($accountant)->postJson("/api/expenses/{$expense->id}/cancel", [])
            ->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->actingAs($accountant)->postJson("/api/expenses/{$expense->id}/cancel", ['reason' => 'Facture en double'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Facture en double');

        $this->actingAs($accountant)->putJson("/api/expenses/{$expense->id}", $this->payload($category))
            ->assertStatus(409)->assertJsonPath('error_code', 'expense_cancelled');
        $this->actingAs($accountant)->postJson("/api/expenses/{$expense->id}/cancel", ['reason' => 'Encore'])
            ->assertStatus(409)->assertJsonPath('error_code', 'expense_already_cancelled');

        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'cancelled_by' => $accountant->id]);
    }

    #[Test]
    public function la_liste_se_filtre_par_categorie_mode_statut_et_recherche(): void
    {
        $accountant = $this->userWithRole('accountant');
        $chalk = ExpenseCategory::factory()->create();
        $books = ExpenseCategory::factory()->create();

        Expense::factory()->create(['expense_category_id' => $chalk->id, 'label' => 'Craies blanches', 'supplier_name' => 'Papeterie Centrale']);
        Expense::factory()->create(['expense_category_id' => $books->id, 'label' => "Registres d'appel", 'method' => 'cheque', 'invoice_reference' => 'FAC-777']);
        Expense::factory()->cancelled()->create(['expense_category_id' => $chalk->id, 'label' => 'Craies en double']);

        $this->actingAs($accountant)->getJson('/api/expenses')->assertOk()->assertJsonCount(3, 'data');
        $this->actingAs($accountant)->getJson("/api/expenses?expense_category_id={$books->id}")->assertJsonCount(1, 'data');
        $this->actingAs($accountant)->getJson('/api/expenses?method=cheque')->assertJsonCount(1, 'data');
        $this->actingAs($accountant)->getJson('/api/expenses?status=cancelled')->assertJsonCount(1, 'data')->assertJsonPath('data.0.label', 'Craies en double');
        $this->actingAs($accountant)->getJson('/api/expenses?search=Papeterie')->assertJsonCount(1, 'data');
        $this->actingAs($accountant)->getJson('/api/expenses?search=FAC-777')->assertJsonCount(1, 'data')->assertJsonPath('data.0.label', "Registres d'appel");
    }

    #[Test]
    public function la_liste_suit_l_annee_scolaire_de_septembre_a_aout(): void
    {
        $accountant = $this->userWithRole('accountant');
        $category = ExpenseCategory::factory()->create();

        // Achat de rentree en septembre : il appartient a l'annee 2025-2026 meme si les trimestres commencent en octobre.
        Expense::factory()->create(['expense_category_id' => $category->id, 'label' => 'Rentrée', 'spent_at' => '2025-09-15']);
        Expense::factory()->create(['expense_category_id' => $category->id, 'label' => 'Fin août', 'spent_at' => '2026-08-31']);
        Expense::factory()->create(['expense_category_id' => $category->id, 'label' => 'Année suivante', 'spent_at' => '2026-09-01']);
        Expense::factory()->create(['expense_category_id' => $category->id, 'label' => 'Année précédente', 'spent_at' => '2025-08-31']);

        $this->actingAs($accountant)->getJson('/api/expenses?academic_year=2025-2026&sort=spent_at&direction=asc')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.label', 'Rentrée')
            ->assertJsonPath('data.1.label', 'Fin août');

        $this->actingAs($accountant)->getJson('/api/expenses?month=2025-09')->assertJsonCount(1, 'data');
    }

    #[Test]
    public function les_fournisseurs_deja_saisis_sont_proposes_du_plus_recent_au_plus_ancien(): void
    {
        $accountant = $this->userWithRole('accountant');
        $category = ExpenseCategory::factory()->create();
        Expense::factory()->create(['expense_category_id' => $category->id, 'supplier_name' => 'Imprimerie Nationale', 'spent_at' => '2026-01-10']);
        Expense::factory()->create(['expense_category_id' => $category->id, 'supplier_name' => 'Papeterie Centrale', 'spent_at' => '2026-03-10']);
        Expense::factory()->create(['expense_category_id' => $category->id, 'supplier_name' => 'Papeterie Centrale', 'spent_at' => '2026-02-10']);
        Expense::factory()->create(['expense_category_id' => $category->id, 'supplier_name' => null]);

        $this->actingAs($accountant)->getJson('/api/expenses/suppliers')
            ->assertOk()
            ->assertExactJson(['data' => ['Papeterie Centrale', 'Imprimerie Nationale']]);
    }

    #[Test]
    public function les_droits_separent_la_consultation_de_la_saisie(): void
    {
        $category = ExpenseCategory::factory()->create();
        $expense = Expense::factory()->create(['expense_category_id' => $category->id]);

        $teacher = $this->userWithRole('teacher');

        $this->actingAs($teacher)->getJson('/api/expenses')->assertForbidden();
        $this->actingAs($teacher)->getJson("/api/expenses/{$expense->id}")->assertForbidden();
        $this->actingAs($teacher)->postJson('/api/expenses', $this->payload($category))->assertForbidden();
        $this->actingAs($teacher)->getJson('/api/expenses/summary')->assertForbidden();
    }

    #[Test]
    public function sans_jeton_les_depenses_sont_refusees(): void
    {
        $this->getJson('/api/expenses')->assertUnauthorized();
    }

    #[Test]
    public function un_jeton_parent_n_atteint_pas_les_depenses(): void
    {
        $token = $this->studentWithPassword()->createToken('portail')->plainTextToken;

        $this->withToken($token)->getJson('/api/expenses')->assertForbidden();
    }
}
