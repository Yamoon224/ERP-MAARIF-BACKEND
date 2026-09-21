<?php

namespace App\Domains\Expenses\Services;

use App\Domains\Accounting\Enums\PaymentMethod;
use App\Domains\Expenses\Contracts\ExpenseReportRepositoryContract;
use App\Domains\Expenses\Support\ExpensePeriod;
use App\Domains\Shared\Support\Period;

/** Bilan des depenses : total, repartition par categorie et par mode de paiement, evolution mensuelle. */
final class ExpenseReportService
{
    public function __construct(private readonly ExpenseReportRepositoryContract $reports) {}

    /**
     * @param  array<string, mixed>  $filters  academic_year / term_id / month
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        $period = ExpensePeriod::resolve($filters);

        return [
            'period' => $period?->toArray(),
            'total' => $this->reports->total($period),
            'by_category' => $this->reports->totalsByCategory($period),
            'by_method' => $this->byMethod($period),
            'by_month' => $this->byMonth($period),
        ];
    }

    /**
     * Tous les modes apparaissent, meme a zero : un tableau dont les lignes
     * changent d'une periode a l'autre est illisible.
     *
     * @return list<array{key: string, label: string, total: float, count: int}>
     */
    private function byMethod(?Period $period): array
    {
        $totals = $this->reports->totalsByMethod($period);

        return array_map(fn (PaymentMethod $method) => [
            'key' => $method->value,
            'label' => $method->label(),
            'total' => $totals[$method->value]['total'] ?? 0.0,
            'count' => $totals[$method->value]['count'] ?? 0,
        ], PaymentMethod::cases());
    }

    /**
     * Mois par mois. Sur une periode connue, chaque mois y figure (a zero
     * s'il n'y a rien eu) pour que l'histogramme ait toujours la meme echelle.
     *
     * @return list<array{month: string, total: float}>
     */
    private function byMonth(?Period $period): array
    {
        $spent = $this->reports->totalsByMonth($period);

        if ($period !== null) {
            $month = $period->from->startOfMonth();
            $last = $period->to->startOfMonth();

            for (; $month <= $last; $month = $month->addMonth()) {
                $spent[$month->format('Y-m')] ??= 0.0;
            }
            ksort($spent);
        }

        return array_map(
            fn (string $month, float $total) => ['month' => $month, 'total' => $total],
            array_keys($spent),
            array_values($spent),
        );
    }
}
