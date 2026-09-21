<?php

namespace App\Domains\Expenses\Services;

use App\Domains\Expenses\Contracts\ExpenseCategoryRepositoryContract;
use App\Domains\Expenses\Exceptions\ExpenseException;
use App\Models\ExpenseCategory;
use Illuminate\Support\Collection;

/** Postes de depense de l'etablissement. */
final class ExpenseCategoryService
{
    public function __construct(private readonly ExpenseCategoryRepositoryContract $categories) {}

    /** @return Collection<int, ExpenseCategory> */
    public function list(bool $activeOnly = false): Collection
    {
        return $this->categories->all($activeOnly);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): ExpenseCategory
    {
        return $this->categories->findOrFail($this->categories->create($data)->id);
    }

    /** @param  array<string, mixed>  $data */
    public function update(ExpenseCategory $category, array $data): ExpenseCategory
    {
        return $this->categories->update($category, $data);
    }

    /** Une categorie qui porte des depenses est desactivee, pas supprimee : l'historique reste lisible. */
    public function delete(ExpenseCategory $category): void
    {
        if ($this->categories->hasExpenses($category)) {
            throw ExpenseException::categoryInUse();
        }

        $this->categories->delete($category);
    }
}
