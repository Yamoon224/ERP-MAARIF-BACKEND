<?php

namespace App\Domains\Expenses\Contracts;

use App\Domains\Shared\Support\Period;

/**
 * Agregats de depenses. Seules les depenses valides comptent : une depense
 * annulee garde sa trace mais sort de tous les totaux.
 */
interface ExpenseReportRepositoryContract
{
    /** @return array{total: float, count: int} */
    public function total(?Period $period): array;

    /**
     * Totaux par categorie, les plus lourdes d'abord ; une categorie sans
     * depense sur la periode n'apparait pas.
     *
     * @return list<array{id: string, name: string, total: float, count: int}>
     */
    public function totalsByCategory(?Period $period): array;

    /** @return array<string, array{total: float, count: int}> mode de paiement => totaux */
    public function totalsByMethod(?Period $period): array;

    /** @return array<string, float> `YYYY-MM` => total depense ce mois-la */
    public function totalsByMonth(?Period $period): array;
}
