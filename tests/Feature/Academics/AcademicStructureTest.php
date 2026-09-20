<?php

namespace Tests\Feature\Academics;

use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AcademicStructureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function un_administrateur_cree_une_classe(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->postJson('/api/classes', [
            'name' => '5eme B',
            'level' => '5eme',
            'academic_year' => '2025-2026',
        ])->assertCreated()->assertJsonPath('data.name', '5eme B');
    }

    #[Test]
    public function un_administrateur_cree_une_matiere_avec_son_coefficient(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->postJson('/api/subjects', [
            'name' => 'Philosophie',
            'code' => 'PHILO',
            'coefficient' => 3,
        ])->assertCreated()->assertJsonPath('data.coefficient', 3);
    }

    /** Marquer un trimestre comme courant retire ce statut a tous les autres. */
    #[Test]
    public function un_seul_trimestre_est_courant_a_la_fois(): void
    {
        $admin = $this->userWithRole('admin');
        $existing = Term::factory()->create([
            'name' => 'Trimestre existant',
            'academic_year' => '2020-2021',
            'is_current' => true,
        ]);

        $this->actingAs($admin)->postJson('/api/terms', [
            'name' => 'Trimestre teste',
            'academic_year' => '2020-2021',
            'starts_at' => now()->addMonths(3)->toDateString(),
            'ends_at' => now()->addMonths(6)->toDateString(),
            'is_current' => true,
        ])->assertCreated();

        $this->assertFalse($existing->refresh()->is_current);
    }

    #[Test]
    public function un_enseignant_ne_peut_pas_administrer_la_structure_scolaire(): void
    {
        $teacher = $this->userWithRole('teacher');

        $this->actingAs($teacher)->postJson('/api/classes', [
            'name' => '5eme C',
            'level' => '5eme',
            'academic_year' => '2025-2026',
        ])->assertStatus(403);
    }
}
