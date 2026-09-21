<?php

namespace App\Domains\Expenses\Contracts;

use App\Models\Expense;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ExpenseRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters  expense_category_id, method, status (valid|cancelled), search,
     *                                         academic_year/term_id/month (sur la date de la depense), sort, direction
     * @return LengthAwarePaginator<int, Expense>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findOrFail(string $id): Expense;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Expense;

    /** @param  array<string, mixed>  $attributes */
    public function update(Expense $expense, array $attributes): Expense;

    /** Dernier numero de depense commencant par ce prefixe, pour en deduire le suivant. */
    public function lastNumber(string $prefix): ?string;

    /**
     * Fournisseurs deja saisis, du plus recent au plus ancien : propose en
     * saisie semi-automatique plutot que d'imposer une table de fournisseurs.
     *
     * @return list<string>
     */
    public function suppliers(): array;
}
