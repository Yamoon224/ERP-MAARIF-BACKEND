<?php

namespace App\Domains\Expenses\Repositories;

use App\Domains\Expenses\Contracts\ExpenseRepositoryContract;
use App\Domains\Expenses\Support\ExpensePeriod;
use App\Domains\Shared\Support\Sort;
use App\Models\Expense;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class EloquentExpenseRepository implements ExpenseRepositoryContract
{
    /** @var array<string, string> */
    private const SORTABLE = [
        'spent_at' => 'spent_at',
        'amount' => 'amount',
    ];

    /** @var list<string> */
    private const RELATIONS = ['category:id,name', 'recordedBy:id,name'];

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->with(self::RELATIONS)
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'spent_at', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(string $id): Expense
    {
        return Expense::query()->with(self::RELATIONS)->findOrFail($id);
    }

    public function create(array $attributes): Expense
    {
        return Expense::create($attributes);
    }

    public function update(Expense $expense, array $attributes): Expense
    {
        $expense->update($attributes);

        return $this->findOrFail($expense->id);
    }

    public function lastNumber(string $prefix): ?string
    {
        return Expense::query()
            ->where('number', 'like', "{$prefix}%")
            ->orderByDesc('number')
            ->value('number');
    }

    public function suppliers(): array
    {
        return Expense::query()
            ->whereNotNull('supplier_name')
            ->groupBy('supplier_name')
            ->orderByRaw('max(spent_at) desc')
            ->limit(100)
            ->pluck('supplier_name')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Expense>
     */
    private function filtered(array $filters): Builder
    {
        $status = $filters['status'] ?? null;
        $period = ExpensePeriod::resolve($filters);

        return Expense::query()
            ->when($period, fn ($query, $period) => $period->constrain($query, 'expenses.spent_at'))
            ->when($filters['expense_category_id'] ?? null, fn ($query, $id) => $query->where('expense_category_id', $id))
            ->when($filters['method'] ?? null, fn ($query, $method) => $query->where('method', $method))
            ->when($status === 'valid', fn ($query) => $query->whereNull('cancelled_at'))
            ->when($status === 'cancelled', fn ($query) => $query->whereNotNull('cancelled_at'))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($sub) => $sub
                    ->where('number', 'like', "%{$search}%")
                    ->orWhere('label', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%")
                    ->orWhere('invoice_reference', 'like', "%{$search}%"),
            ));
    }
}
