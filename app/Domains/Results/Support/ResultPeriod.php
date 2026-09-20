<?php

namespace App\Domains\Results\Support;

/**
 * Periode sur laquelle se calculent des resultats : un trimestre, un semestre
 * ou l'annee entiere.
 *
 * Une annee compte trois trimestres, donc les deux semestres se chevauchent
 * sur le deuxieme : le 1er semestre regroupe les trimestres 1 et 2, le 2eme
 * les trimestres 2 et 3. Ce chevauchement est un choix de l'etablissement, pas
 * une propriete du calendrier.
 */
final class ResultPeriod
{
    public const TERM = 'term';

    public const SEMESTER = 'semester';

    public const ANNUAL = 'annual';

    /**
     * @param  list<string>  $termIds  trimestres couverts, dans l'ordre chronologique
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $key,
        public readonly string $label,
        public readonly string $academicYear,
        public readonly array $termIds,
    ) {}

    /** @return list<string> */
    public static function kinds(): array
    {
        return [self::TERM, self::SEMESTER, self::ANNUAL];
    }

    public function isAnnual(): bool
    {
        return $this->kind === self::ANNUAL;
    }

    /** @return array{kind: string, key: string, label: string, academic_year: string, term_ids: list<string>} */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'key' => $this->key,
            'label' => $this->label,
            'academic_year' => $this->academicYear,
            'term_ids' => $this->termIds,
        ];
    }
}
