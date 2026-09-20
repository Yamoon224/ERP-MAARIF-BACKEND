<?php

namespace Tests\Unit\Results;

use App\Domains\Results\Support\AverageCalculator;
use App\Domains\Results\Support\Mention;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AverageRankTest extends TestCase
{
    #[Test]
    public function les_ex_aequo_partagent_le_meme_rang_et_le_suivant_saute(): void
    {
        $ranks = AverageCalculator::rank(['a' => 15.0, 'b' => 15.0, 'c' => 12.0, 'd' => 9.5]);

        $this->assertSame(['a' => 1, 'b' => 1, 'c' => 3, 'd' => 4], $ranks);
    }

    #[Test]
    public function un_eleve_sans_moyenne_n_est_pas_classe_et_ne_decale_personne(): void
    {
        $ranks = AverageCalculator::rank(['a' => null, 'b' => 11.0, 'c' => 13.0]);

        $this->assertSame(['a' => null, 'b' => 2, 'c' => 1], $ranks);
    }

    #[Test]
    public function la_mention_suit_les_seuils_de_la_moyenne(): void
    {
        $this->assertNull(Mention::forAverage(null));
        $this->assertSame('Insuffisant', Mention::forAverage(9.99));
        $this->assertSame('Passable', Mention::forAverage(10));
        $this->assertSame('Assez bien', Mention::forAverage(12));
        $this->assertSame('Bien', Mention::forAverage(14));
        $this->assertSame('Très bien', Mention::forAverage(16));
    }
}
