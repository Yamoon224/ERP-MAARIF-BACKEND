<?php

namespace App\Domains\Expenses\Support;

use App\Domains\Shared\Support\Period;

/**
 * Periode d'une liste ou d'un total de depenses.
 *
 * Une annee scolaire entiere s'etend du 1er septembre au 31 aout, et non des
 * dates des trimestres : les fournitures s'achetent avant la rentree, et un
 * achat de septembre pour une annee qui commence en octobre appartient bien a
 * cette annee. Un trimestre ou un mois garde son intervalle exact.
 */
final class ExpensePeriod
{
    /** @param  array<string, mixed>  $filters  academic_year / term_id / month */
    public static function resolve(array $filters): ?Period
    {
        if (Period::isWholeYear($filters)) {
            return Period::forAcademicYearCalendar($filters['academic_year']);
        }

        return Period::fromFilters($filters);
    }
}
