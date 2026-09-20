<?php

namespace App\Domains\Results\Support;

use App\Models\Grade;
use Illuminate\Support\Collection;

/**
 * Moyennes d'un eleve sur une periode, calculees a partir de ses notes.
 *
 * Regle unique, du trimestre a l'annee : chaque note est ramenee sur 20, on
 * fait la moyenne par matiere et par trimestre, puis la moyenne de la matiere
 * sur la periode est la moyenne de ses moyennes trimestrielles. La moyenne
 * generale est ensuite ponderee par les coefficients. Sur un seul trimestre,
 * cela redonne exactement le bulletin (voir BulletinService).
 *
 * Les trimestres sont moyennes entre eux et non leurs notes mises en commun :
 * sinon un trimestre riche en devoirs peserait plus lourd qu'un trimestre qui
 * en compte peu, alors qu'ils valent autant. Un trimestre sans note dans une
 * matiere est ignore plutot que compte pour zero, comme dans le bulletin.
 */
final class AverageCalculator
{
    private function __construct() {}

    /**
     * @param  Collection<int, Grade>  $grades  notes d'un seul eleve, matiere chargee
     * @return array{subjects: list<array{subject_id: string, subject: string, code: string, coefficient: float, average: float, grades_count: int}>, overall_average: float|null, grades_count: int}
     */
    public static function compute(Collection $grades): array
    {
        $subjects = [];
        $weightedSum = 0.0;
        $totalCoefficient = 0.0;

        foreach ($grades->groupBy('subject_id') as $subjectGrades) {
            $subject = $subjectGrades->first()->subject;

            $termAverages = $subjectGrades
                ->groupBy('term_id')
                ->map(fn (Collection $termGrades): float => round((float) $termGrades->avg(fn (Grade $grade) => $grade->normalizedOn20()), 2));

            $average = round((float) $termAverages->avg(), 2);
            $coefficient = (float) $subject->coefficient;

            $subjects[] = [
                'subject_id' => $subject->id,
                'subject' => $subject->name,
                'code' => $subject->code,
                'coefficient' => $coefficient,
                'average' => $average,
                'grades_count' => $subjectGrades->count(),
            ];

            $weightedSum += $average * $coefficient;
            $totalCoefficient += $coefficient;
        }

        return [
            'subjects' => $subjects,
            'overall_average' => $totalCoefficient > 0 ? round($weightedSum / $totalCoefficient, 2) : null,
            'grades_count' => $grades->count(),
        ];
    }

    /**
     * Rang de chaque moyenne, classement "a la competition" : deux eleves a
     * egalite partagent le meme rang et le suivant saute (1, 2, 2, 4). Une
     * moyenne absente n'est pas classee.
     *
     * @param  array<string, float|null>  $averages  cle (identifiant) => moyenne
     * @return array<string, int|null>
     */
    public static function rank(array $averages): array
    {
        $ranked = array_filter($averages, fn ($average) => $average !== null);

        $ranks = [];
        foreach ($averages as $key => $average) {
            $ranks[$key] = $average === null
                ? null
                : 1 + count(array_filter($ranked, fn ($other) => $other > $average));
        }

        return $ranks;
    }
}
