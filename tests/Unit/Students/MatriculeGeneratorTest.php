<?php

namespace Tests\Unit\Students;

use App\Domains\Students\Support\MatriculeGenerator;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MatriculeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function il_genere_un_premier_matricule_pour_une_annee_sans_eleve(): void
    {
        $matricule = MatriculeGenerator::generate(Carbon::create(2026, 9, 1));

        $this->assertSame('MAA-2026-000001', $matricule);
    }

    #[Test]
    public function il_incremente_a_partir_du_dernier_matricule_de_l_annee(): void
    {
        Student::factory()->create(['matricule' => 'MAA-2026-000041']);

        $matricule = MatriculeGenerator::generate(Carbon::create(2026, 9, 1));

        $this->assertSame('MAA-2026-000042', $matricule);
    }

    #[Test]
    public function il_ne_melange_pas_les_sequences_de_deux_annees(): void
    {
        Student::factory()->create(['matricule' => 'MAA-2025-000999']);

        $matricule = MatriculeGenerator::generate(Carbon::create(2026, 1, 1));

        $this->assertSame('MAA-2026-000001', $matricule);
    }
}
