<?php

namespace App\Domains\Accounting\Observers;

use App\Domains\Accounting\Services\TuitionService;
use App\Models\SchoolClass;

/**
 * Quand le tarif mensuel d'une classe change (par la route des frais ou par
 * la modification de la classe), les mois non regles de ses eleves passent au
 * nouveau tarif. Sans cela, les impayes d'une classe dont le tarif vient
 * d'etre fixe n'apparaitraient dans aucun rapport tant que personne n'a ouvert
 * le releve de chaque eleve.
 */
final class SchoolClassFeeObserver
{
    public function __construct(private readonly TuitionService $tuition) {}

    public function saved(SchoolClass $schoolClass): void
    {
        if ($schoolClass->wasChanged('monthly_fee')) {
            $this->tuition->syncClass($schoolClass->id);
        }
    }
}
