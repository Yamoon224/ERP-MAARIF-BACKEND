<?php

namespace Tests\Unit\Grades;

use App\Models\Grade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GradeNormalizationTest extends TestCase
{
    #[Test]
    public function elle_ramene_une_note_sur_un_bareme_different_a_20(): void
    {
        $grade = new Grade(['value' => 8, 'max_value' => 10]);

        $this->assertSame(16.0, $grade->normalizedOn20());
    }

    #[Test]
    public function une_note_deja_sur_20_reste_inchangee(): void
    {
        $grade = new Grade(['value' => 14.5, 'max_value' => 20]);

        $this->assertSame(14.5, $grade->normalizedOn20());
    }

    #[Test]
    public function un_bareme_nul_ne_produit_pas_de_division_par_zero(): void
    {
        $grade = new Grade(['value' => 5, 'max_value' => 0]);

        $this->assertSame(0.0, $grade->normalizedOn20());
    }
}
