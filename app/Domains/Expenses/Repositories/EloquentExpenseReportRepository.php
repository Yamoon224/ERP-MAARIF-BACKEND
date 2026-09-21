<?php

namespace App\Domains\Expenses\Repositories;

use App\Domains\Expenses\Contracts\ExpenseReportRepositoryContract;
use App\Domains\Shared\Support\Period;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Builder;

final class EloquentExpenseReportRepository implements ExpenseReportRepositoryContract
{
    public function total(?Period $period): array
    {
        $row = $this->valid($period)
            ->selectRaw('coalesce(sum(expenses.amount), 0) as total_amount, count(*) as expenses_count')
            ->first();

        return ['total' => round((float) $row->total_amount, 2), 'count' => (int) $row->expenses_count];
    }

    public function totalsByCategory(?Period $period): array
    {
        return $this->valid($period)
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->selectRaw('expense_categories.id as category_id, expense_categories.name as category_name, sum(expenses.amount) as total_amount, count(*) as expenses_count')
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->orderByDesc('total_amount')
            ->orderBy('expense_categories.name')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->category_id,
                'name' => $row->category_name,
                'total' => round((float) $row->total_amount, 2),
                'count' => (int) $row->expenses_count,
            ])
            ->all();
    }

    public function totalsByMethod(?Period $period): array
    {
        $totals = [];

        $rows = $this->valid($period)
            ->selectRaw('expenses.method as bucket, sum(expenses.amount) as total_amount, count(*) as expenses_count')
            ->groupBy('expenses.method')
            ->get();

        foreach ($rows as $row) {
            $totals[$row->bucket] = ['total' => round((float) $row->total_amount, 2), 'count' => (int) $row->expenses_count];
        }

        return $totals;
    }

    public function totalsByMonth(?Period $period): array
    {
        $byMonth = [];

        foreach ($this->valid($period)->get(['expenses.spent_at', 'expenses.amount']) as $expense) {
            $month = $expense->spent_at->format('Y-m');
            $byMonth[$month] = round(($byMonth[$month] ?? 0.0) + (float) $expense->amount, 2);
        }

        ksort($byMonth);

        return $byMonth;
    }

    /** @return Builder<Expense> */
    private function valid(?Period $period): Builder
    {
        $query = Expense::query()->whereNull('expenses.cancelled_at');

        return $period?->constrain($query, 'expenses.spent_at') ?? $query;
    }
}
