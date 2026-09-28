<?php

namespace App\Domains\Results\Services;

use App\Domains\Results\Contracts\GradeScaleRepositoryContract;
use App\Domains\Results\Enums\PromotionDecisionType;
use App\Domains\Results\Exceptions\ResultsException;
use App\Domains\Results\Support\Mention;
use App\Models\GradeScaleBand;
use Illuminate\Support\Collection;

/**
 * Bareme de passage et d'appreciation, configurable par classe : chaque
 * tranche de moyenne annuelle donne un libelle (ex. "Redouble", "Bien") et,
 * optionnellement, une decision de passage suggeree.
 *
 * Une classe sans bareme configure retombe entierement sur le comportement
 * global existant (Mention::forAverage() et le seuil de passage global) :
 * ce repli est deliberement le comportement par defaut, pour qu'aucune
 * classe existante ne change de resultat tant que l'administration n'a pas
 * explicitement configure de bareme pour elle.
 */
final class GradeScaleService
{
    public function __construct(private readonly GradeScaleRepositoryContract $bands) {}

    /** @return Collection<int, GradeScaleBand> */
    public function forClass(string $schoolClassId): Collection
    {
        return $this->bands->forClass($schoolClassId);
    }

    /**
     * @param  list<array{min_average: float, max_average: float, label: string, decision: string|null}>  $bands
     * @return Collection<int, GradeScaleBand>
     */
    public function save(string $schoolClassId, array $bands): Collection
    {
        $this->assertValid($bands);

        return $this->bands->replaceForClass($schoolClassId, $bands);
    }

    /** Copie le bareme d'une classe vers d'autres, pour ne pas ressaisir la meme configuration classe par classe. */
    public function duplicate(string $sourceClassId, array $targetClassIds): void
    {
        $bands = $this->forClass($sourceClassId)
            ->map(fn (GradeScaleBand $band) => [
                'min_average' => $band->min_average,
                'max_average' => $band->max_average,
                'label' => $band->label,
                'decision' => $band->decision?->value,
            ])
            ->all();

        foreach ($targetClassIds as $targetClassId) {
            $this->bands->replaceForClass($targetClassId, $bands);
        }
    }

    /** Appreciation a afficher pour cette moyenne, dans le bareme de la classe si elle en a un, sinon le bareme global. */
    public function appreciation(?string $schoolClassId, ?float $average): ?string
    {
        if ($average === null) {
            return null;
        }

        return $this->matchBand($schoolClassId, $average)?->label ?? Mention::forAverage($average);
    }

    /** Decision de passage suggeree pour cette moyenne, dans le bareme de la classe si elle en couvre une, sinon le seuil global. */
    public function suggestedDecision(?string $schoolClassId, ?float $average, float $globalPassMark): ?PromotionDecisionType
    {
        if ($average === null) {
            return null;
        }

        $band = $this->matchBand($schoolClassId, $average);
        if ($band?->decision !== null) {
            return $band->decision;
        }

        return $average >= $globalPassMark ? PromotionDecisionType::Admitted : PromotionDecisionType::Repeat;
    }

    private function matchBand(?string $schoolClassId, float $average): ?GradeScaleBand
    {
        if ($schoolClassId === null) {
            return null;
        }

        return $this->forClass($schoolClassId)
            ->first(fn (GradeScaleBand $band) => $average >= $band->min_average && $average <= $band->max_average);
    }

    /** @param  list<array{min_average: float, max_average: float, label: string, decision: string|null}>  $bands */
    private function assertValid(array $bands): void
    {
        $sorted = collect($bands)->sortBy('min_average')->values();

        foreach ($sorted as $index => $band) {
            if ($band['min_average'] > $band['max_average']) {
                throw ResultsException::invalidGradeScaleRange($band['min_average'], $band['max_average']);
            }

            $next = $sorted->get($index + 1);
            if ($next !== null && $band['max_average'] >= $next['min_average']) {
                throw ResultsException::overlappingGradeScaleBands();
            }
        }
    }
}
