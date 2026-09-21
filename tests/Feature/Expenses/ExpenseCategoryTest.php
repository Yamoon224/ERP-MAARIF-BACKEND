<?php

namespace Tests\Feature\Expenses;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Database\Seeders\ExpenseCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Postes de depense : une liste courte, propre a chaque etablissement. */
class ExpenseCategoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_liste_donne_le_nombre_de_depenses_de_chaque_categorie_par_ordre_alphabetique(): void
    {
        $accountant = $this->userWithRole('accountant');
        $registers = ExpenseCategory::factory()->create(['name' => 'Registres']);
        ExpenseCategory::factory()->create(['name' => 'Craies']);
        Expense::factory()->count(2)->create(['expense_category_id' => $registers->id]);

        $this->actingAs($accountant)->getJson('/api/expense-categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Craies')
            ->assertJsonPath('data.0.expenses_count', 0)
            ->assertJsonPath('data.1.name', 'Registres')
            ->assertJsonPath('data.1.expenses_count', 2);
    }

    #[Test]
    public function la_liste_peut_ne_garder_que_les_categories_actives(): void
    {
        $accountant = $this->userWithRole('accountant');
        ExpenseCategory::factory()->create(['name' => 'Active']);
        ExpenseCategory::factory()->inactive()->create(['name' => 'Ancienne']);

        $this->actingAs($accountant)->getJson('/api/expense-categories?active_only=1')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active');
    }

    #[Test]
    public function on_cree_renomme_et_desactive_une_categorie(): void
    {
        $accountant = $this->userWithRole('accountant');

        $id = $this->actingAs($accountant)->postJson('/api/expense-categories', ['name' => 'Sorties pédagogiques', 'description' => 'Transport, entrées.'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Sorties pédagogiques')
            ->assertJsonPath('data.is_active', true)
            ->json('data.id');

        $this->actingAs($accountant)->putJson("/api/expense-categories/{$id}", ['name' => 'Sorties et voyages', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Sorties et voyages')
            ->assertJsonPath('data.is_active', false);
    }

    #[Test]
    public function le_nom_est_obligatoire_et_unique_mais_reste_modifiable_sur_soi_meme(): void
    {
        $accountant = $this->userWithRole('accountant');
        $existing = ExpenseCategory::factory()->create(['name' => 'Craies']);
        $other = ExpenseCategory::factory()->create(['name' => 'Registres']);

        $this->actingAs($accountant)->postJson('/api/expense-categories', [])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->actingAs($accountant)->postJson('/api/expense-categories', ['name' => 'Craies'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->actingAs($accountant)->putJson("/api/expense-categories/{$other->id}", ['name' => 'Craies'])->assertStatus(422);
        $this->actingAs($accountant)->putJson("/api/expense-categories/{$existing->id}", ['name' => 'Craies', 'description' => 'Boîtes de 100'])
            ->assertOk()->assertJsonPath('data.description', 'Boîtes de 100');
    }

    #[Test]
    public function une_categorie_vide_se_supprime_mais_pas_une_categorie_utilisee(): void
    {
        $accountant = $this->userWithRole('accountant');
        $empty = ExpenseCategory::factory()->create();
        $used = ExpenseCategory::factory()->create();
        Expense::factory()->create(['expense_category_id' => $used->id]);

        $this->actingAs($accountant)->deleteJson("/api/expense-categories/{$empty->id}")->assertNoContent();
        $this->assertDatabaseMissing('expense_categories', ['id' => $empty->id]);

        $this->actingAs($accountant)->deleteJson("/api/expense-categories/{$used->id}")
            ->assertStatus(409)->assertJsonPath('error_code', 'expense_category_in_use');
        $this->assertDatabaseHas('expense_categories', ['id' => $used->id]);
    }

    #[Test]
    public function seul_le_personnel_autorise_gere_les_categories(): void
    {
        ExpenseCategory::factory()->create();
        $teacher = $this->userWithRole('teacher');

        $this->actingAs($teacher)->getJson('/api/expense-categories')->assertForbidden();
        $this->actingAs($teacher)->postJson('/api/expense-categories', ['name' => 'Nouvelle'])->assertForbidden();
    }

    #[Test]
    public function les_categories_de_depart_se_chargent_sans_doublon(): void
    {
        $this->seed(ExpenseCategorySeeder::class);
        $this->seed(ExpenseCategorySeeder::class);

        $this->assertSame(count(ExpenseCategorySeeder::DEFAULTS), ExpenseCategory::count());
        $this->assertDatabaseHas('expense_categories', ['name' => 'Fournitures scolaires']);
    }
}
