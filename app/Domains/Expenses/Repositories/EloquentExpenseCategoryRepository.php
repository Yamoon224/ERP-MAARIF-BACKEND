<?php

namespace App\Domains\Expenses\Repositories;

use App\Domains\Expenses\Contracts\ExpenseCategoryRepositoryContract;
use App\Models\ExpenseCategory;
use Illuminate\Support\Collection;

final class EloquentExpenseCategoryRepository implements ExpenseCategoryRepositoryContract
{
    public function all(bool $activeOnly = false): Collection
    {
        return ExpenseCategory::query()
            ->withCount('expenses')
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get();
    }

    public function findOrFail(string $id): ExpenseCategory
    {
        return ExpenseCategory::query()->withCount('expenses')->findOrFail($id);
    }

    public function create(array $attributes): ExpenseCategory
    {
        return ExpenseCategory::create($attributes);
    }

    public function update(ExpenseCategory $category, array $attributes): ExpenseCategory
    {
        $category->update($attributes);

        return $this->findOrFail($category->id);
    }

    public function delete(ExpenseCategory $category): void
    {
        $category->delete();
    }

    public function hasExpenses(ExpenseCategory $category): bool
    {
        return $category->expenses()->exists();
    }
}
