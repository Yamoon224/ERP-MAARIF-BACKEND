<?php

namespace App\Domains\Accounting\Contracts;

use App\Domains\Shared\Support\Period;
use App\Models\TuitionInstallment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Agregats comptables. Deux axes distincts, volontairement separes :
 *
 *  - l'encaissement se mesure a la date de paiement (`payments.paid_at`) :
 *    "combien est entre en caisse ce mois-ci" ;
 *  - la scolarite due se mesure au mois de l'echeance
 *    (`tuition_installments.month`) : "combien etait du pour ce mois, et
 *    combien est regle".
 */
interface AccountingReportRepositoryContract
{
    /**
     * Encaissements valides (hors annules) sur la periode.
     *
     * @param  array<string, mixed>  $filters  school_class_id
     * @return array{total: float, count: int}
     */
    public function collected(?Period $period, array $filters = []): array;

    /**
     * Encaissements valides ventiles par formule de paiement ou par mode.
     *
     * @param  'period_type'|'method'  $column
     * @param  array<string, mixed>  $filters
     * @return array<string, array{total: float, count: int}> cle de l'enum => totaux
     */
    public function collectedBy(string $column, ?Period $period, array $filters = []): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, float> `YYYY-MM` => total encaisse ce mois-la
     */
    public function collectedByMonth(?Period $period, array $filters = []): array;

    /**
     * Scolarite due pour les mois de la periode, et part deja reglee.
     *
     * @param  array<string, mixed>  $filters
     * @return array{total: float, settled: float}
     */
    public function expected(?Period $period, array $filters = []): array;

    /**
     * Impayes : echeances non reglees dont le mois est deja passe, regroupees
     * par inscription (une ligne par eleve endette), les plus lourdes d'abord.
     *
     * @param  array<string, mixed>  $filters  school_class_id, search
     * @return LengthAwarePaginator<int, TuitionInstallment>
     */
    public function arrears(?Period $period, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $filters
     * @return array{amount: float, students: int, months: int}
     */
    public function arrearsTotals(?Period $period, array $filters = []): array;
}
