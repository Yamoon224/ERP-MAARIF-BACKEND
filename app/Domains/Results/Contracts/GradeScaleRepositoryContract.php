<?php

namespace App\Domains\Results\Contracts;

use App\Models\GradeScaleBand;
use Illuminate\Support\Collection;

interface GradeScaleRepositoryContract
{
    /** @return Collection<int, GradeScaleBand> triees par moyenne minimale croissante */
    public function forClass(string $schoolClassId): Collection;

    /**
     * Remplace entierement le bareme de la classe (tout ou rien) : un bareme
     * a moitie enregistre laisserait des tranches incoherentes.
     *
     * @param  list<array{min_average: float, max_average: float, label: string, decision: string|null}>  $bands
     * @return Collection<int, GradeScaleBand>
     */
    public function replaceForClass(string $schoolClassId, array $bands): Collection;
}
