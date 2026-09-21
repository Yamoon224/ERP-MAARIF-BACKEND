<?php

namespace App\Domains\Expenses\Contracts;

use App\Models\ExpenseCategory;
use Illuminate\Support\Collection;

interface ExpenseCategoryRepositoryContract
{
    /**
     * Toutes les categories avec leur nombre de depenses, par ordre alphabetique.
     *
     * @return Collection<int, ExpenseCategory>
     */
    public function all(bool $activeOnly = false): Collection;

    public function findOrFail(string $id): ExpenseCategory;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): ExpenseCategory;

    /** @param  array<string, mixed>  $attributes */
    public function update(ExpenseCategory $category, array $attributes): ExpenseCategory;

    public function delete(ExpenseCategory $category): void;

    public function hasExpenses(ExpenseCategory $category): bool;
}
